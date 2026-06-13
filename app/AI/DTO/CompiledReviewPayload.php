<?php

namespace App\AI\DTO;

readonly class CompiledReviewPayload
{
    /**
     * @param array<string, mixed> $submission
     * @param array<string, mixed> $context
     * @param array<string, mixed> $artifacts
     * @param array<int, array<string, mixed>> $criteria
     * @param array<int, array<string, mixed>> $serverResults
     * @param array<int, string> $unsupportedChecks
     */
    public function __construct(
        public int $reviewRunId,
        public string $provider,
        public string $model,
        public array $submission,
        public array $context,
        public array $artifacts,
        public array $criteria,
        public array $serverResults,
        public array $unsupportedChecks,
        public ?string $customPrompt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'review_run_id' => $this->reviewRunId,
            'provider' => $this->provider,
            'model' => $this->model,
            'submission' => $this->submission,
            'context' => $this->context,
            'artifacts' => $this->artifacts,
            'criteria' => $this->criteria,
            'server_results' => $this->serverResults,
            'unsupported_checks' => $this->unsupportedChecks,
            'custom_prompt' => $this->customPrompt,
        ];
    }
}
