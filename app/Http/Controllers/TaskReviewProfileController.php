<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskReviewProfileRequest;
use App\Models\Course;
use App\Models\Task;
use App\Models\TaskReviewProfile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskReviewProfileController extends Controller
{
    use AuthorizesRequests;

    private function defaultSupportedFormats(): array
    {
        return config('ai.supported_extensions', ['docx', 'xlsx', 'csv', 'tsv', 'zip', 'php', 'js', 'ts', 'py', 'java', 'cs']);
    }

    public function show(Task $task)
    {
        $course = Course::find($task->course_id);
        $this->authorize('manage-review-profile', [Task::class, $course]);

        $profile = $task->reviewProfile;

        return response()->json([
            'task_id' => $task->id,
            'profile' => [
                'enabled' => $profile?->enabled ?? false,
                'rubric' => $profile?->rubric_json ?? [],
                'custom_prompt' => $profile?->custom_prompt,
                'supported_formats' => $profile?->supported_formats_json ?? $this->defaultSupportedFormats(),
                'version' => $profile?->version ?? 1,
            ],
        ]);
    }

    public function update(UpdateTaskReviewProfileRequest $request, Task $task)
    {
        $course = Course::find($task->course_id);
        $this->authorize('manage-review-profile', [Task::class, $course]);

        $validated = $request->validated();

        /** @var TaskReviewProfile $profile */
        $profile = $task->reviewProfile()->firstOrNew();
        $profile->task_id = $task->id;
        $profile->enabled = (bool) $validated['enabled'];
        $profile->rubric_json = $validated['rubric'];
        $profile->custom_prompt = $validated['custom_prompt'] ?? null;
        $profile->supported_formats_json = $validated['supported_formats'] ?? $this->defaultSupportedFormats();
        $profile->version = $profile->exists ? $profile->version + 1 : 1;
        $profile->save();

        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.review_profile')]),
            'profile' => $profile,
        ]);
    }
}
