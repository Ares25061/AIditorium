<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskReviewProfileRequest;
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

    public function show(Task $task)
    {
        $this->authorize('manage-review-profile', $task);

        $profile = $task->reviewProfile;

        return response()->json([
            'task_id' => $task->id,
            'profile' => [
                'enabled' => $profile?->enabled ?? true,
                'rubric' => $profile?->rubric_json ?? $this->defaultRubric($task),
                'custom_prompt' => $profile?->custom_prompt,
                'supported_formats' => $profile?->supported_formats_json ?? $this->defaultSupportedFormats(),
                'version' => $profile?->version ?? 1,
            ],
        ]);
    }

    public function update(UpdateTaskReviewProfileRequest $request, Task $task)
    {
        $this->authorize('manage-review-profile', $task);

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
