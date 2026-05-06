<?php

namespace App\Http\Controllers;

use App\Enums\ReviewRunStatus;
use App\Http\Requests\QueueAiReviewRequest;
use App\Jobs\ProcessAiReviewRunJob;
use App\Models\AiReviewRun;
use App\Models\File;
use App\Models\Grade;
use App\Models\Task;
use App\Models\TaskReviewProfile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AiReviewController extends Controller
{
    use AuthorizesRequests;

    public function queue(QueueAiReviewRequest $request, Task $task, File $file)
    {
        $this->authorize('run-ai-review', $task);

        if ($file->task_id !== $task->id || $file->type !== 'submission') {
            return response()->json(['error' => __('messages.ai_review_invalid_submission')], 422);
        }

        $profile = $this->getOrCreateReviewProfile($task);
        if (!$profile->enabled) {
            $profile->enabled = true;
            $profile->save();
        }

        $extension = strtolower((string) ($file->extension ?: pathinfo((string) ($file->original_name ?: $file->path), PATHINFO_EXTENSION)));
        $supportedFormats = $profile->supported_formats_json ?? [];
        if (!empty($supportedFormats) && !in_array($extension, $supportedFormats, true)) {
            return response()->json(['error' => __('messages.ai_review_format_not_supported')], 422);
        }

        $forceRecheck = (bool) ($request->validated()['force_recheck'] ?? false);
        $existingRun = AiReviewRun::where('task_id', $task->id)
            ->where('file_id', $file->id)
            ->latest()
            ->first();

        if (!$forceRecheck && $existingRun && in_array($existingRun->status, [
            ReviewRunStatus::QUEUED,
            ReviewRunStatus::EXTRACTING,
            ReviewRunStatus::ANALYZING,
            ReviewRunStatus::COMPLETED,
        ], true)) {
            return response()->json([
                'message' => __('messages.ai_review_exists'),
                'review' => $existingRun,
            ]);
        }

        $reviewRun = AiReviewRun::create([
            'course_id' => $task->course_id,
            'discipline_id' => $task->discipline_id,
            'task_id' => $task->id,
            'file_id' => $file->id,
            'student_id' => $file->user_id,
            'requested_by' => Auth::id(),
            'status' => ReviewRunStatus::QUEUED,
            'provider' => (string) config('ai.provider'),
            'model' => (string) config('ai.model'),
        ]);

        $this->dispatchReviewRun($reviewRun->id);

        return response()->json([
            'message' => __('messages.ai_review_queued'),
            'review' => $reviewRun,
        ], 202);
    }

    public function index(Task $task, Request $request)
    {
        $this->authorize('view-ai-reviews', $task);

        $reviews = AiReviewRun::where('task_id', $task->id)
            ->with(['file', 'student:id,name,email', 'requester:id,name,email'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['reviews' => $reviews]);
    }

    public function show(AiReviewRun $review)
    {
        $task = $review->task ?? Task::find($review->task_id);
        if (!$task) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        $this->authorize('view-ai-reviews', $task);

        return response()->json([
            'review' => $review->load(['file', 'student:id,name,email', 'requester:id,name,email', 'task']),
        ]);
    }

    public function applyGrade(AiReviewRun $review)
    {
        $task = $review->task ?? Task::find($review->task_id);
        if (!$task) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        $this->authorize('apply-ai-review-grade', $task);

        if ($review->status !== ReviewRunStatus::COMPLETED || $review->recommended_score === null) {
            return response()->json(['error' => __('messages.ai_review_not_completed')], 422);
        }

        $grade = Grade::updateOrCreate(
            [
                'user_id' => $review->student_id,
                'course_id' => $review->course_id,
                'task_id' => $review->task_id,
                'type' => 'AI',
            ],
            [
                'discipline_id' => $review->discipline_id,
                'file_id' => $review->file_id,
                'grade' => $review->recommended_score,
                'graded_by' => Auth::id(),
                'graded_at' => now(),
            ],
        );

        return response()->json([
            'message' => __('messages.ai_review_grade_applied'),
            'grade' => $grade->load(['student', 'grader', 'task', 'discipline', 'file']),
        ]);
    }

    private function dispatchReviewRun(int $reviewRunId): void
    {
        if (app()->runningUnitTests()) {
            ProcessAiReviewRunJob::dispatchSync($reviewRunId);

            return;
        }

        $dispatchMode = (string) config('ai.dispatch_mode', 'after_response');

        if ($dispatchMode === 'sync') {
            ProcessAiReviewRunJob::dispatchSync($reviewRunId);

            return;
        }

        if ($dispatchMode === 'after_response') {
            ProcessAiReviewRunJob::dispatchAfterResponse($reviewRunId);

            return;
        }

        if ($dispatchMode === 'queue') {
            $dispatch = ProcessAiReviewRunJob::dispatch($reviewRunId);
            $connection = config('ai.queue_connection');
            $queue = config('ai.queue');

            if (is_string($connection) && $connection !== '') {
                $dispatch->onConnection($connection);
            }

            if (is_string($queue) && $queue !== '') {
                $dispatch->onQueue($queue);
            }

            return;
        }

        throw new RuntimeException("Unsupported AI dispatch mode [{$dispatchMode}].");
    }

    private function getOrCreateReviewProfile(Task $task): TaskReviewProfile
    {
        /** @var TaskReviewProfile $profile */
        $profile = $task->reviewProfile()->firstOrCreate(
            ['task_id' => $task->id],
            [
                'enabled' => true,
                'rubric_json' => $this->defaultRubric($task),
                'custom_prompt' => null,
                'supported_formats_json' => config('ai.supported_extensions', []),
                'version' => 1,
            ],
        );

        return $profile;
    }

    private function defaultRubric(Task $task): array
    {
        $maxScore = max(1, (int) ($task->scores ?: 100));
        $requirements = (int) round($maxScore * 0.4);
        $quality = (int) round($maxScore * 0.4);
        $independence = max(0, $maxScore - $requirements - $quality);

        return [
            [
                'id' => 'requirements',
                'label' => 'Соответствие заданию',
                'description' => 'Проверь, насколько работа решает поставленную задачу и учитывает требования из описания.',
                'checks' => [],
                'weight' => $requirements,
            ],
            [
                'id' => 'quality',
                'label' => 'Качество выполнения',
                'description' => 'Оцени структуру, аккуратность, аргументацию и качество реализации.',
                'checks' => [],
                'weight' => $quality,
            ],
            [
                'id' => 'independence',
                'label' => 'Самостоятельность и выводы',
                'description' => 'Проверь, есть ли в работе собственные выводы, объяснения и осмысленное выполнение.',
                'checks' => [],
                'weight' => $independence,
            ],
        ];
    }
}
