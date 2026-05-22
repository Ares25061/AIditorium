<?php

namespace App\Jobs;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTO\CompiledReviewPayload;
use App\AI\DTO\CriterionResult;
use App\AI\DTO\ReviewProfile;
use App\AI\DTO\StructuredCriterion;
use App\AI\Services\AIModelResolver;
use App\AI\Services\CriteriaCompiler;
use App\AI\Services\ReviewResultAssembler;
use App\AI\Services\ServerCriterionEvaluator;
use App\AI\Services\SubmissionExtractor;
use App\Enums\ReviewRunStatus;
use App\Models\AiReviewRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAiReviewRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly int $reviewRunId,
    ) {
        $this->tries = max(1, (int) config('ai.job_tries', 3));
        $this->timeout = max(60, (int) config('ai.job_timeout', 1800));
    }

    public function backoff(): array
    {
        $backoff = (string) config('ai.job_backoff', '30,90,180');
        $values = array_values(array_filter(
            array_map(static fn (string $value) => (int) trim($value), explode(',', $backoff)),
            static fn (int $value) => $value >= 0,
        ));

        return $values !== [] ? $values : [30, 90, 180];
    }

    public function handle(
        SubmissionExtractor $extractor,
        CriteriaCompiler $criteriaCompiler,
        AIModelResolver $aiModelResolver,
        ServerCriterionEvaluator $serverCriterionEvaluator,
        ReviewResultAssembler $resultAssembler,
        LLMClientInterface $llmClient,
    ): void {
        /** @var AiReviewRun|null $reviewRun */
        $reviewRun = AiReviewRun::with(['task.reviewProfile', 'file', 'student'])->find($this->reviewRunId);
        if (!$reviewRun) {
            return;
        }

        $reviewRun->update([
            'status' => ReviewRunStatus::EXTRACTING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $artifacts = $extractor->extract($reviewRun->file);
            $reviewRun->update([
                'status' => ReviewRunStatus::ANALYZING,
                'extracted_artifacts_json' => $artifacts,
            ]);

            $profile = $reviewRun->task?->reviewProfile;
            if (!$profile || !$profile->enabled) {
                throw new \RuntimeException('Task review profile is disabled.');
            }

            $reviewProfile = new ReviewProfile(
                enabled: $profile->enabled,
                criteria: array_map(
                    static fn (array $item, int $index) => StructuredCriterion::fromArray($item, $index),
                    $profile->rubric_json ?? [],
                    array_keys($profile->rubric_json ?? []),
                ),
                customPrompt: $profile->custom_prompt,
                supportedFormats: $profile->supported_formats_json ?? [],
                version: (int) $profile->version,
            );

            $compiled = $criteriaCompiler->compile($reviewProfile);
            $serverEvaluation = $serverCriterionEvaluator->evaluate($reviewRun->file, $compiled['criteria']);
            $unsupportedChecks = array_values(array_unique([
                ...$compiled['unsupported_checks'],
                ...$serverEvaluation['unsupported_checks'],
            ]));
            $aiModel = $aiModelResolver->resolveProviderModel($reviewRun->provider, $reviewRun->model);

            $payload = new CompiledReviewPayload(
                reviewRunId: $reviewRun->id,
                provider: $aiModel['provider'],
                model: $aiModel['model'],
                submission: [
                    'task_id' => $reviewRun->task_id,
                    'task_name' => $reviewRun->task?->name,
                    'student_id' => $reviewRun->student_id,
                    'student_name' => $reviewRun->student?->name,
                    'file_id' => $reviewRun->file_id,
                    'file_name' => $reviewRun->file?->original_name,
                    'file_extension' => $reviewRun->file?->extension,
                ],
                artifacts: $artifacts,
                criteria: $serverEvaluation['llm_criteria'],
                serverResults: array_map(
                    static fn (CriterionResult $result) => $result->toArray(),
                    $serverEvaluation['criterion_results'],
                ),
                unsupportedChecks: $unsupportedChecks,
                customPrompt: $profile->custom_prompt,
            );

            $reviewRun->update([
                'criteria_snapshot_json' => [
                    'profile_version' => $profile->version,
                    'ai_model_key' => $aiModel['key'],
                    'provider' => $aiModel['provider'],
                    'model' => $aiModel['model'],
                    'rubric' => $compiled['criteria'],
                    'llm_criteria' => $serverEvaluation['llm_criteria'],
                    'server_results' => array_map(
                        static fn (CriterionResult $result) => $result->toArray(),
                        $serverEvaluation['criterion_results'],
                    ),
                    'unsupported_checks' => $unsupportedChecks,
                ],
            ]);

            $llmResult = $serverEvaluation['llm_criteria'] !== []
                ? $llmClient->analyze($payload)
                : null;

            $result = $resultAssembler->assemble(
                criteria: $compiled['criteria'],
                serverResults: $serverEvaluation['criterion_results'],
                llmResult: $llmResult,
                unsupportedChecks: $unsupportedChecks,
            );

            $reviewRun->update([
                'status' => ReviewRunStatus::COMPLETED,
                'result_json' => $result->toArray(),
                'summary' => $result->summary,
                'recommended_score' => $result->recommendedScore,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($this->shouldRetryException($exception) && $this->attempts() < $this->tries) {
                $reviewRun->update([
                    'status' => ReviewRunStatus::QUEUED,
                    'error_message' => $exception->getMessage(),
                    'finished_at' => null,
                ]);

                throw $exception;
            }

            $reviewRun->update([
                'status' => ReviewRunStatus::FAILED,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function shouldRetryException(Throwable $exception): bool
    {
        return preg_match(
            '/OpenRouter connection failed|cURL error 28|timed out|timeout|too many requests|429|5\d\d/i',
            $exception->getMessage(),
        ) === 1;
    }
}
