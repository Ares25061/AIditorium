<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
