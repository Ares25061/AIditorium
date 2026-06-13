<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_dashboard_data(): void
    {
        $admin = User::whereHas('role', fn ($query) => $query->where('name', Roles::ADMIN->value))->firstOrFail();
        $student = $this->createRegularUser();

        $course = Course::create([
            'creator_id' => $admin->id,
            'name' => 'Админ курс',
            'invite_code' => 'IC-'.Str::upper(Str::random(8)),
            'invite_code_teacher' => 'TC-'.Str::upper(Str::random(8)),
            'description' => 'Курс для проверки админ-панели',
            'status' => 'active',
            'is_closed' => false,
            'slug' => 'admin-course-'.Str::lower(Str::random(6)),
        ]);
        $course->users()->attach($student->id, ['role' => 'student']);

        $discipline = Discipline::create([
            'course_id' => $course->id,
            'name' => 'Админ дисциплина',
            'hours' => 32,
            'slug' => 'admin-discipline-'.Str::lower(Str::random(6)),
            'created_by' => $admin->id,
        ]);

        $task = Task::create([
            'user_id' => $admin->id,
            'course_id' => $course->id,
            'discipline_id' => $discipline->id,
            'name' => 'Админ задание',
            'description' => 'Описание задания',
            'scores' => 100,
            'deadline' => now()->addWeek(),
        ]);

        File::create([
            'path' => 'submissions/admin-test.txt',
            'original_name' => 'admin-test.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => 120,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'task_id' => $task->id,
            'type' => 'submission',
            'is_public' => false,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.courses', 1)
            ->assertJsonPath('stats.disciplines', 1)
            ->assertJsonPath('stats.tasks', 1)
            ->assertJsonPath('stats.files', 1)
            ->assertJsonFragment(['name' => 'Админ курс'])
            ->assertJsonFragment(['name' => 'Админ дисциплина'])
            ->assertJsonFragment(['name' => 'Админ задание'])
            ->assertJsonFragment(['original_name' => 'admin-test.txt'])
            ->assertJsonFragment(['role' => 'student']);
    }

    public function test_regular_user_cannot_view_dashboard_data(): void
    {
        $user = $this->createRegularUser();

        $this->actingAs($user, 'api')
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error', 'Доступ разрешен только администратору.');
    }

    public function test_admin_can_reset_course_background(): void
    {
        Storage::fake('public');

        $admin = User::whereHas('role', fn ($query) => $query->where('name', Roles::ADMIN->value))->firstOrFail();
        $course = Course::create([
            'creator_id' => $admin->id,
            'name' => 'Курс с баннером',
            'invite_code' => 'IC-'.Str::upper(Str::random(8)),
            'invite_code_teacher' => 'TC-'.Str::upper(Str::random(8)),
            'description' => 'Курс для проверки сброса баннера',
            'status' => 'active',
            'is_closed' => false,
            'slug' => 'banner-course-'.Str::lower(Str::random(6)),
        ]);

        Storage::disk('public')->put('backs/banner.jpg', 'fake image');
        $file = File::create([
            'path' => 'backs/banner.jpg',
            'original_name' => 'banner.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 10,
            'user_id' => $admin->id,
            'type' => 'background',
            'is_public' => true,
        ]);
        $course->update(['background_logo_id' => $file->id]);

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/admin/course/{$course->id}/background")
            ->assertOk()
            ->assertJsonPath('course.background_logo_id', null);

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing('backs/banner.jpg');
    }

    private function createRegularUser(): User
    {
        $userRole = Role::where('name', Roles::USER->value)->firstOrFail();

        return User::factory()->create([
            'role_id' => $userRole->id,
        ]);
    }
}
