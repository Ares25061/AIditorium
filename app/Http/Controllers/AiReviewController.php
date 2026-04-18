<?php

namespace App\Http\Controllers;

use App\Enums\ReviewRunStatus;
use App\Http\Requests\QueueAiReviewRequest;
use App\Jobs\ProcessAiReviewRunJob;
use App\Models\AiReviewRun;
use App\Models\Course;
use App\Models\File;
use App\Models\Grade;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiReviewController extends Controller
{
    use AuthorizesRequests;

    public function queue(QueueAiReviewRequest $request, Task $task, File $file)
    {
        $course = Course::find($task->course_id);
        $this->authorize('run-ai-review', [Task::class, $course]);

        if ($file->task_id !== $task->id || $file->type !== 'submission') {
            return response()->json(['error' => __('messages.ai_review_invalid_submission')], 422);
        }

        $profile = $task->reviewProfile;
        if (!$profile || !$profile->enabled) {
            return response()->json(['error' => __('messages.ai_review_profile_missing')], 422);
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

        ProcessAiReviewRunJob::dispatch($reviewRun->id);

        return response()->json([
            'message' => __('messages.ai_review_queued'),
            'review' => $reviewRun,
        ], 202);
    }

    public function index(Task $task, Request $request)
    {
        $course = Course::find($task->course_id);
        $this->authorize('view-ai-reviews', [Task::class, $course]);

        $reviews = AiReviewRun::where('task_id', $task->id)
            ->with(['file', 'student:id,name,email', 'requester:id,name,email'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['reviews' => $reviews]);
    }

    public function show(AiReviewRun $review)
    {
        $course = $review->course ?? Course::find($review->course_id);
        $this->authorize('view-ai-reviews', [Task::class, $course]);

        return response()->json([
            'review' => $review->load(['file', 'student:id,name,email', 'requester:id,name,email', 'task']),
        ]);
    }

    public function applyGrade(AiReviewRun $review)
    {
        $course = $review->course ?? Course::find($review->course_id);
        $this->authorize('apply-ai-review-grade', [Task::class, $course]);

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
}
