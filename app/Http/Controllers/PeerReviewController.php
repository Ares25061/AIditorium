<?php

namespace App\Http\Controllers;

use App\Enums\CourseUsersRoleEnum;
use App\Models\File;
use App\Models\PeerReviewAssignment;
use App\Models\PeerReviewResult;
use App\Models\PeerReviewSetting;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeerReviewController extends Controller
{
    use AuthorizesRequests;

    public function myAssignments()
    {
        $assignments = PeerReviewAssignment::query()
            ->where('reviewer_id', Auth::id())
            ->with([
                'course:id,name,slug',
                'discipline:id,name,slug',
                'task:id,name,task_number,scores',
                'file:id,original_name,path',
                'targetUser:id,name,email',
                'result',
            ])
            ->latest()
            ->get()
            ->map(fn (PeerReviewAssignment $assignment) => $this->serializeAssignment($assignment));

        return response()->json([
            'assignments' => $assignments,
        ]);
    }

    public function showSettings(Task $task)
    {
        $this->authorize('view-submissions', $task);

        return response()->json([
            'settings' => $this->serializeSettings($this->getOrCreateSettings($task)),
        ]);
    }

    public function updateSettings(Request $request, Task $task)
    {
        $this->authorize('view-submissions', $task);

        $validated = $request->validate([
            'mode' => 'sometimes|string|in:blind,open',
            'reviews_per_student' => 'sometimes|integer|min:1|max:10',
            'reviewsPerStudent' => 'sometimes|integer|min:1|max:10',
            'allow_score' => 'sometimes|boolean',
            'allowScore' => 'sometimes|boolean',
            'instructions' => 'nullable|string|max:5000',
        ]);

        $settings = $this->getOrCreateSettings($task);
        $settings->fill([
            'mode' => $validated['mode'] ?? $settings->mode,
            'reviews_per_student' => $validated['reviews_per_student']
                ?? $validated['reviewsPerStudent']
                ?? $settings->reviews_per_student,
            'allow_score' => $validated['allow_score']
                ?? $validated['allowScore']
                ?? $settings->allow_score,
            'instructions' => $validated['instructions'] ?? $settings->instructions,
        ])->save();

        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.task')]),
            'settings' => $this->serializeSettings($settings),
        ]);
    }

    public function taskAssignments(Task $task)
    {
        $this->authorize('view-submissions', $task);

        $assignments = PeerReviewAssignment::query()
            ->where('task_id', $task->id)
            ->with([
                'course:id,name,slug',
                'discipline:id,name,slug',
                'task:id,name,task_number,scores',
                'file:id,original_name,path',
                'targetUser:id,name,email',
                'result',
            ])
            ->latest()
            ->get()
            ->map(fn (PeerReviewAssignment $assignment) => $this->serializeAssignment($assignment, false));

        return response()->json([
            'assignments' => $assignments,
        ]);
    }

    public function replaceTaskAssignments(Request $request, Task $task)
    {
        $this->authorize('view-submissions', $task);

        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.id' => 'sometimes|string|max:191',
            'assignments.*.assignment_key' => 'sometimes|string|max:191',
            'assignments.*.reviewer_id' => 'required|integer|exists:users,id',
            'assignments.*.target_user_id' => 'required|integer|exists:users,id',
            'assignments.*.file_id' => 'required|integer|exists:files,id',
            'assignments.*.blind' => 'sometimes|boolean',
            'assignments.*.allow_score' => 'sometimes|boolean',
            'assignments.*.allowScore' => 'sometimes|boolean',
            'assignments.*.max_score' => 'sometimes|integer|min:0|max:10000',
            'assignments.*.maxScore' => 'sometimes|integer|min:0|max:10000',
            'assignments.*.instructions' => 'nullable|string|max:5000',
        ]);

        $studentIds = $this->courseStudentIds($task);
        $createdAssignments = DB::transaction(function () use ($validated, $task, $studentIds) {
            PeerReviewAssignment::where('task_id', $task->id)->delete();

            return collect($validated['assignments'])
                ->map(function (array $payload) use ($task, $studentIds) {
                    $reviewerId = (int) $payload['reviewer_id'];
                    $targetUserId = (int) $payload['target_user_id'];

                    if ($reviewerId === $targetUserId) {
                        throw ValidationException::withMessages([
                            'assignments' => ['Студент не может проверять свою работу.'],
                        ]);
                    }

                    if (!in_array($reviewerId, $studentIds, true) || !in_array($targetUserId, $studentIds, true)) {
                        throw ValidationException::withMessages([
                            'assignments' => ['Проверяющий и автор работы должны быть студентами этого курса.'],
                        ]);
                    }

                    $submission = File::query()
                        ->whereKey($payload['file_id'])
                        ->where('task_id', $task->id)
                        ->where('user_id', $targetUserId)
                        ->where('type', 'submission')
                        ->first();

                    if (!$submission) {
                        throw ValidationException::withMessages([
                            'assignments' => ['Файл для взаимопроверки не найден среди сдач этого задания.'],
                        ]);
                    }

                    return PeerReviewAssignment::create([
                        'assignment_key' => (string) ($payload['assignment_key'] ?? $payload['id'] ?? ''),
                        'course_id' => $task->course_id,
                        'discipline_id' => $task->discipline_id,
                        'task_id' => $task->id,
                        'reviewer_id' => $reviewerId,
                        'target_user_id' => $targetUserId,
                        'file_id' => $submission->id,
                        'blind' => (bool) ($payload['blind'] ?? true),
                        'allow_score' => (bool) ($payload['allow_score'] ?? $payload['allowScore'] ?? true),
                        'max_score' => (int) ($payload['max_score'] ?? $payload['maxScore'] ?? $task->scores ?? 100),
                        'instructions' => $payload['instructions'] ?? null,
                    ]);
                })
                ->all();
        });

        $assignments = PeerReviewAssignment::query()
            ->whereIn('id', collect($createdAssignments)->pluck('id'))
            ->with([
                'course:id,name,slug',
                'discipline:id,name,slug',
                'task:id,name,task_number,scores',
                'file:id,original_name,path',
                'targetUser:id,name,email',
                'result',
            ])
            ->latest()
            ->get()
            ->map(fn (PeerReviewAssignment $assignment) => $this->serializeAssignment($assignment, false));

        return response()->json([
            'message' => 'Задания для взаимопроверки сохранены.',
            'assignments' => $assignments,
        ], 201);
    }

    public function taskResults(Task $task)
    {
        $this->authorize('view-submissions', $task);

        $results = PeerReviewResult::query()
            ->where('task_id', $task->id)
            ->with('assignment')
            ->latest()
            ->get()
            ->map(fn (PeerReviewResult $result) => $this->serializeResult($result));

        return response()->json([
            'results' => $results,
        ]);
    }

    public function saveResult(Request $request)
    {
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:peer_review_assignments,id',
            'grade' => 'nullable|numeric|min:0',
            'comment' => 'required|string|max:5000',
        ]);

        $assignment = PeerReviewAssignment::query()
            ->whereKey($validated['assignment_id'])
            ->where('reviewer_id', Auth::id())
            ->firstOrFail();

        $grade = array_key_exists('grade', $validated) && $validated['grade'] !== null
            ? (float) $validated['grade']
            : null;

        if (!$assignment->allow_score) {
            $grade = null;
        }

        if ($grade !== null && $grade > $assignment->max_score) {
            throw ValidationException::withMessages([
                'grade' => ["Оценка должна быть от 0 до {$assignment->max_score}."],
            ]);
        }

        $result = PeerReviewResult::updateOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'task_id' => $assignment->task_id,
                'reviewer_id' => $assignment->reviewer_id,
                'target_user_id' => $assignment->target_user_id,
                'file_id' => $assignment->file_id,
                'grade' => $grade,
                'comment' => trim($validated['comment']),
            ],
        );

        return response()->json([
            'message' => 'Взаимопроверка сохранена.',
            'result' => $this->serializeResult($result),
        ]);
    }

    private function getOrCreateSettings(Task $task): PeerReviewSetting
    {
        return PeerReviewSetting::firstOrCreate(
            ['task_id' => $task->id],
            [
                'mode' => 'blind',
                'reviews_per_student' => 2,
                'allow_score' => true,
                'instructions' => null,
            ],
        );
    }

    /**
     * @return array<int, int>
     */
    private function courseStudentIds(Task $task): array
    {
        return $task->course
            ? $task->course
                ->users()
                ->wherePivot('role', CourseUsersRoleEnum::STUDENT->value)
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettings(PeerReviewSetting $settings): array
    {
        return [
            'enabled' => true,
            'mode' => $settings->mode === 'open' ? 'open' : 'blind',
            'reviewsPerStudent' => $settings->reviews_per_student,
            'reviews_per_student' => $settings->reviews_per_student,
            'allowScore' => $settings->allow_score,
            'allow_score' => $settings->allow_score,
            'instructions' => $settings->instructions ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignment(PeerReviewAssignment $assignment, bool $hideBlindTarget = true): array
    {
        $hideTarget = $hideBlindTarget && $assignment->blind;
        $result = $assignment->result ? $this->serializeResult($assignment->result) : null;

        if ($hideTarget && $result) {
            $result['target_user_id'] = null;
        }

        return [
            'id' => $assignment->id,
            'assignment_key' => $assignment->assignment_key,
            'task_id' => $assignment->task_id,
            'course_id' => $assignment->course_id,
            'discipline_id' => $assignment->discipline_id,
            'reviewer_id' => $assignment->reviewer_id,
            'target_user_id' => $hideTarget ? null : $assignment->target_user_id,
            'target_user_name' => $hideTarget ? null : $assignment->targetUser?->name,
            'target_user_email' => $hideTarget ? null : $assignment->targetUser?->email,
            'file_id' => $assignment->file_id,
            'file_name' => $assignment->file?->original_name
                ?: $assignment->file?->path
                ?: 'Файл',
            'task_name' => $assignment->task?->name ?? 'Задание',
            'max_score' => $assignment->max_score,
            'course_name' => $assignment->course?->name ?? '',
            'discipline_name' => $assignment->discipline?->name ?? '',
            'course_identifier' => $assignment->course?->slug ?: $assignment->course_id,
            'discipline_identifier' => $assignment->discipline?->slug ?: $assignment->discipline_id,
            'task_number' => $assignment->task?->task_number ?? $assignment->task_id,
            'blind' => $assignment->blind,
            'allow_score' => $assignment->allow_score,
            'allowScore' => $assignment->allow_score,
            'instructions' => $assignment->instructions ?? '',
            'created_at' => optional($assignment->created_at)->toISOString(),
            'updated_at' => optional($assignment->updated_at)->toISOString(),
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResult(PeerReviewResult $result): array
    {
        return [
            'id' => $result->id,
            'assignment_id' => $result->assignment_id,
            'task_id' => $result->task_id,
            'reviewer_id' => $result->reviewer_id,
            'target_user_id' => $result->target_user_id,
            'file_id' => $result->file_id,
            'grade' => $result->grade,
            'comment' => $result->comment,
            'created_at' => optional($result->created_at)->toISOString(),
            'updated_at' => optional($result->updated_at)->toISOString(),
        ];
    }
}
