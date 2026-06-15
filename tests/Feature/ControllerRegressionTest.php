<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ControllerRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_missing_course_and_discipline_endpoints_return_404(): void
    {
        $user = $this->createUser();

        $this->actingAs($user, 'api')
            ->getJson('/api/course/999999')
            ->assertNotFound();

        $this->actingAs($user, 'api')
            ->getJson('/api/discipline/999999')
            ->assertNotFound();
    }

    public function test_task_comments_endpoint_uses_task_course_authorization(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'task' => $task, 'course' => $course] = $this->createCourseContext();

        Comment::create([
            'user_id' => $teacher->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'body' => 'Комментарий к заданию',
        ]);

        $this->actingAs($student, 'api')
            ->postJson('/api/comment/viewTask', [
                'task_id' => $task->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_comment_author_can_delete_public_and_private_comments(): void
    {
        ['student' => $student, 'task' => $task, 'course' => $course] = $this->createCourseContext();

        $submission = File::create([
            'path' => 'submissions/comment-delete.txt',
            'original_name' => 'comment-delete.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 120,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'submission',
            'is_public' => false,
        ]);

        $publicComment = Comment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'body' => 'Публичный комментарий',
        ]);

        $privateComment = Comment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'file_id' => $submission->id,
            'body' => 'Личный комментарий',
        ]);

        $this->actingAs($student, 'api')
            ->deleteJson("/api/comment/{$publicComment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Комментарий успешно удален!');

        $this->actingAs($student, 'api')
            ->deleteJson("/api/comment/{$privateComment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Комментарий успешно удален!');

        $this->assertDatabaseMissing('comments', ['id' => $publicComment->id]);
        $this->assertDatabaseMissing('comments', ['id' => $privateComment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        ['student' => $student, 'task' => $task, 'course' => $course] = $this->createCourseContext();
        $otherStudent = $this->createUser();
        $otherStudent->courses()->attach($course->id, ['role' => 'student']);

        $comment = Comment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'body' => 'Чужой комментарий',
        ]);

        $this->actingAs($otherStudent, 'api')
            ->deleteJson("/api/comment/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_task_review_profile_defaults_follow_supported_extensions_config(): void
    {
        ['teacher' => $teacher, 'task' => $task] = $this->createCourseContext();

        $response = $this->actingAs($teacher, 'api')
            ->getJson("/api/task/{$task->id}/review-profile")
            ->assertOk();

        $formats = $response->json('profile.supported_formats');

        $this->assertContains('cpp', $formats);
        $this->assertContains('cs', $formats);
        $this->assertContains('html', $formats);
        $this->assertContains('css', $formats);
    }

    public function test_view_tasks_rejects_nonexistent_status_sort_column(): void
    {
        ['teacher' => $teacher, 'course' => $course] = $this->createCourseContext();

        $this->actingAs($teacher, 'api')
            ->getJson("/api/task/viewTasks?course_id={$course->id}&sort_by=status&sort_direction=asc")
            ->assertStatus(422);
    }

    public function test_grade_statistics_return_correct_distribution_and_task_name(): void
    {
        ['teacher' => $teacher, 'course' => $course, 'discipline' => $discipline, 'task' => $task] = $this->createCourseContext();

        foreach ([95, 80, 65, 50] as $index => $value) {
            $student = $this->createUser();
            $student->courses()->attach($course->id, ['role' => 'student']);

            Grade::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'task_id' => $task->id,
                'discipline_id' => $discipline->id,
                'type' => 'teacher',
                'grade' => $value,
                'graded_by' => $teacher->id,
                'graded_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->actingAs($teacher, 'api')
            ->postJson('/api/grade/statistics', [
                'course_id' => $course->id,
            ])
            ->assertOk();

        $this->assertSame(4, $response->json('total_grades'));
        $this->assertSame(1, $response->json('grades_distribution.excellent'));
        $this->assertSame(1, $response->json('grades_distribution.good'));
        $this->assertSame(1, $response->json('grades_distribution.satisfactory'));
        $this->assertSame(1, $response->json('grades_distribution.unsatisfactory'));

        $taskStats = collect($response->json('grades_by_task'))->first();
        $this->assertSame($task->name, $taskStats['task_name']);
        $this->assertSame(4, $taskStats['count']);
    }

    public function test_task_store_accepts_iso_deadline_with_timezone(): void
    {
        ['teacher' => $teacher, 'course' => $course, 'discipline' => $discipline] = $this->createCourseContext();

        $deadline = '2026-04-25T18:39:05.2365802+03:00';
        $expectedDeadline = Carbon::parse($deadline)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');

        $response = $this->actingAs($teacher, 'api')
            ->postJson('/api/task', [
                'course_id' => $course->id,
                'discipline_id' => $discipline->id,
                'name' => 'Задание с ISO deadline',
                'scores' => 100,
                'deadline' => $deadline,
            ])
            ->assertOk();

        $taskId = (int) $response->json('task.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'deadline' => $expectedDeadline,
        ]);
    }

    public function test_task_update_normalizes_iso_deadline_with_timezone(): void
    {
        ['teacher' => $teacher, 'task' => $task] = $this->createCourseContext();

        $deadline = '2026-05-01T08:15:00+03:00';
        $expectedDeadline = Carbon::parse($deadline)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');

        $this->actingAs($teacher, 'api')
            ->putJson("/api/task/{$task->id}", [
                'deadline' => $deadline,
            ])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'deadline' => $expectedDeadline,
        ]);
    }

    public function test_task_store_accepts_multiple_attachments(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'course' => $course, 'discipline' => $discipline] = $this->createCourseContext();

        $response = $this->actingAs($teacher, 'api')
            ->post('/api/task', [
                'course_id' => $course->id,
                'discipline_id' => $discipline->id,
                'name' => 'Task with files',
                'attachments' => [
                    UploadedFile::fake()->createWithContent('guide.txt', 'guide'),
                    UploadedFile::fake()->createWithContent('rubric.txt', 'rubric'),
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'task.attachments');

        $taskId = (int) $response->json('task.id');
        $attachments = collect($response->json('task.attachments'));
        $attachmentIds = $attachments->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertSame($attachmentIds->first(), (int) $response->json('task.attachment_id'));
        $this->assertSame(['guide.txt', 'rubric.txt'], $attachments->pluck('original_name')->all());

        foreach ($attachments as $attachment) {
            $this->assertDatabaseHas('files', [
                'id' => $attachment['id'],
                'course_id' => $course->id,
                'task_id' => $taskId,
                'user_id' => $teacher->id,
                'type' => 'task',
                'is_public' => true,
            ]);
            Storage::disk('public')->assertExists($attachment['path']);
        }
    }

    public function test_task_update_adds_and_removes_multiple_attachments(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'course' => $course, 'task' => $task] = $this->createCourseContext();
        Storage::disk('public')->put('tasks/old.txt', 'old');
        $oldFile = File::create([
            'path' => 'tasks/old.txt',
            'original_name' => 'old.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 3,
            'user_id' => $teacher->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'task',
            'is_public' => true,
        ]);
        $task->update(['attachment_id' => $oldFile->id]);

        $response = $this->actingAs($teacher, 'api')
            ->post("/api/task/{$task->id}", [
                '_method' => 'PUT',
                'removed_attachment_ids' => [$oldFile->id],
                'attachments' => [
                    UploadedFile::fake()->createWithContent('new-one.txt', 'one'),
                    UploadedFile::fake()->createWithContent('new-two.txt', 'two'),
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'task.attachments');

        $attachments = collect($response->json('task.attachments'));
        $attachmentIds = $attachments->pluck('id')->map(fn ($id) => (int) $id);

        $this->assertNotContains($oldFile->id, $attachmentIds->all());
        $this->assertSame($attachmentIds->first(), (int) $response->json('task.attachment_id'));
        $this->assertSame(['new-one.txt', 'new-two.txt'], $attachments->pluck('original_name')->all());
        $this->assertDatabaseMissing('files', ['id' => $oldFile->id]);
        Storage::disk('public')->assertMissing('tasks/old.txt');
    }

    public function test_task_attachment_upload_endpoint_accepts_one_file(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'course' => $course, 'task' => $task] = $this->createCourseContext();

        $response = $this->actingAs($teacher, 'api')
            ->post("/api/task/{$task->id}/attachments", [
                'files' => [
                    UploadedFile::fake()->createWithContent('single.pdf', 'content'),
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'task.attachments');

        $attachment = $response->json('task.attachments.0');

        $this->assertSame('single.pdf', $attachment['original_name']);
        $this->assertSame((int) $attachment['id'], (int) $response->json('task.attachment_id'));
        $this->assertDatabaseHas('files', [
            'id' => $attachment['id'],
            'course_id' => $course->id,
            'task_id' => $task->id,
            'user_id' => $teacher->id,
            'type' => 'task',
            'is_public' => true,
        ]);
        Storage::disk('public')->assertExists($attachment['path']);
    }

    public function test_task_attachment_upload_endpoint_rejects_file_over_10_mb(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'task' => $task] = $this->createCourseContext();

        $this->actingAs($teacher, 'api')
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/task/{$task->id}/attachments", [
                'files' => [
                    UploadedFile::fake()->create('large.pdf', (10 * 1024) + 1),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');
    }

    public function test_task_attachments_total_size_is_limited_to_100_mb(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'course' => $course, 'discipline' => $discipline] = $this->createCourseContext();

        $this->actingAs($teacher, 'api')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/task', [
                'course_id' => $course->id,
                'discipline_id' => $discipline->id,
                'name' => 'Task with oversized materials',
                'attachments' => [
                    ...array_map(
                        fn (int $index) => UploadedFile::fake()->create("part-{$index}.bin", 10 * 1024),
                        range(1, 10),
                    ),
                    UploadedFile::fake()->create('extra.bin', 1),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachments');
    }

    public function test_only_task_author_or_assigned_reviewer_can_view_submissions(): void
    {
        Storage::fake('public');

        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'task' => $task] = $this->createCourseContext();
        $otherTeacher = $this->createUser();
        $otherTeacher->courses()->attach($course->id, ['role' => 'teacher']);

        File::create([
            'path' => 'submissions/work.txt',
            'original_name' => 'work.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 4,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'submission',
            'is_public' => false,
        ]);

        $this->actingAs($otherTeacher, 'api')
            ->postJson('/api/task/submissions', [
                'task_id' => $task->id,
            ])
            ->assertForbidden();

        $this->actingAs($teacher, 'api')
            ->postJson('/api/task/submissions', [
                'task_id' => $task->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'submissions.data');

        $this->actingAs($teacher, 'api')
            ->postJson("/api/task/{$task->id}/reviewers", [
                'reviewer_ids' => [$otherTeacher->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'reviewers');

        $this->assertDatabaseHas('task_reviewers', [
            'task_id' => $task->id,
            'user_id' => $otherTeacher->id,
            'assigned_by' => $teacher->id,
        ]);

        $this->actingAs($otherTeacher, 'api')
            ->postJson('/api/task/submissions', [
                'task_id' => $task->id,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'submissions.data');
    }

    public function test_task_reviewer_assignment_rejects_non_teacher(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'task' => $task] = $this->createCourseContext();

        $this->actingAs($teacher, 'api')
            ->postJson("/api/task/{$task->id}/reviewers", [
                'reviewer_ids' => [$student->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reviewer_ids');
    }

    public function test_non_reviewer_teacher_cannot_grade_task_submission(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'discipline' => $discipline, 'task' => $task] = $this->createCourseContext();
        $otherTeacher = $this->createUser();
        $otherTeacher->courses()->attach($course->id, ['role' => 'teacher']);

        $payload = [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'discipline_id' => $discipline->id,
            'grade' => 90,
        ];

        $this->actingAs($otherTeacher, 'api')
            ->postJson('/api/grade', $payload)
            ->assertForbidden();

        $this->actingAs($teacher, 'api')
            ->postJson("/api/task/{$task->id}/reviewers", [
                'reviewer_ids' => [$otherTeacher->id],
            ])
            ->assertOk();

        $this->actingAs($otherTeacher, 'api')
            ->postJson('/api/grade', $payload)
            ->assertCreated();
    }

    public function test_peer_review_assignments_are_visible_to_assigned_student_and_results_are_saved(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'course' => $course, 'task' => $task] = $this->createCourseContext();
        $secondStudent = $this->createUser();
        $secondStudent->courses()->attach($course->id, ['role' => 'student']);

        $firstSubmission = File::create([
            'path' => 'submissions/student-one.txt',
            'original_name' => 'student-one.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 12,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'submission',
            'is_public' => false,
        ]);
        $secondSubmission = File::create([
            'path' => 'submissions/student-two.txt',
            'original_name' => 'student-two.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 12,
            'user_id' => $secondStudent->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'submission',
            'is_public' => false,
        ]);

        $this->actingAs($teacher, 'api')
            ->postJson("/api/task/{$task->id}/peer-review/assignments", [
                'assignments' => [
                    [
                        'id' => 'demo:one',
                        'reviewer_id' => $student->id,
                        'target_user_id' => $secondStudent->id,
                        'file_id' => $secondSubmission->id,
                        'blind' => true,
                        'allow_score' => true,
                        'max_score' => 100,
                    ],
                    [
                        'id' => 'demo:two',
                        'reviewer_id' => $secondStudent->id,
                        'target_user_id' => $student->id,
                        'file_id' => $firstSubmission->id,
                        'blind' => true,
                        'allow_score' => true,
                        'max_score' => 100,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'assignments');

        $assignmentId = $this->actingAs($student, 'api')
            ->getJson('/api/peer-review/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('assignments.0.target_user_id', null)
            ->json('assignments.0.id');

        $this->actingAs($student, 'api')
            ->postJson('/api/peer-review/results', [
                'assignment_id' => $assignmentId,
                'grade' => 88,
                'comment' => 'Контроллер работает корректно, но валидацию можно вынести в FormRequest.',
            ])
            ->assertOk()
            ->assertJsonPath('result.grade', 88);

        $this->actingAs($teacher, 'api')
            ->getJson("/api/task/{$task->id}/peer-review/results")
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.target_user_id', $secondStudent->id);
    }

    private function createUser(): User
    {
        $userRole = Role::where('name', 'user')->firstOrFail();

        return User::factory()->create([
            'role_id' => $userRole->id,
        ]);
    }

    /**
     * @return array{teacher: User, student: User, course: Course, discipline: Discipline, task: Task}
     */
    private function createCourseContext(): array
    {
        $teacher = $this->createUser();
        $student = $this->createUser();

        $course = Course::create([
            'creator_id' => $teacher->id,
            'name' => 'Регрессионный курс',
            'invite_code' => 'IC-'.Str::upper(Str::random(8)),
            'invite_code_teacher' => 'TC-'.Str::upper(Str::random(8)),
            'description' => 'Проверка контроллеров',
            'status' => 'active',
            'is_closed' => false,
            'slug' => 'controller-regression-'.Str::lower(Str::random(6)),
        ]);

        $teacher->courses()->attach($course->id, ['role' => 'teacher']);
        $student->courses()->attach($course->id, ['role' => 'student']);

        $discipline = Discipline::create([
            'course_id' => $course->id,
            'name' => 'Backend',
            'hours' => 24,
            'slug' => 'backend-'.Str::lower(Str::random(6)),
            'description' => 'Тестовая дисциплина',
            'created_by' => $teacher->id,
        ]);

        $task = Task::create([
            'user_id' => $teacher->id,
            'course_id' => $course->id,
            'discipline_id' => $discipline->id,
            'name' => 'Тестовое задание',
            'description' => 'Тестовая задача',
            'scores' => 100,
            'deadline' => now()->addWeek(),
        ]);

        return compact('teacher', 'student', 'course', 'discipline', 'task');
    }
}
