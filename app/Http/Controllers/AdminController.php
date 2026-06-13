<?php

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request->user())) {
            return response()->json([
                'error' => 'Доступ разрешен только администратору.',
            ], 403);
        }

        $users = User::query()
            ->with([
                'role:id,name',
                'courses' => fn ($query) => $query
                    ->select('courses.id', 'courses.name', 'courses.slug', 'courses.status')
                    ->withPivot('role'),
            ])
            ->orderBy('name')
            ->get();

        $courses = Course::query()
            ->with([
                'users:id,name,email',
                'disciplines:id,course_id,name,discipline_number,slug',
                'backgroundLogo:id,path,original_name,mime_type,extension,size_bytes',
            ])
            ->withCount(['users', 'disciplines', 'tasks', 'files'])
            ->orderByDesc('created_at')
            ->get();

        $disciplines = Discipline::query()
            ->with(['course:id,name,slug,status'])
            ->withCount('tasks')
            ->orderByDesc('created_at')
            ->get();

        $tasks = Task::query()
            ->with([
                'course:id,name,slug,status',
                'discipline:id,name,slug,discipline_number',
                'user:id,name,email',
            ])
            ->withCount(['attachments', 'submissions'])
            ->orderByDesc('created_at')
            ->get();

        $files = File::query()
            ->with([
                'owner:id,name,email',
                'course:id,name,slug,status',
                'task:id,name,task_number',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'stats' => [
                'users' => $users->count(),
                'courses' => $courses->count(),
                'disciplines' => $disciplines->count(),
                'tasks' => $tasks->count(),
                'files' => $files->count(),
            ],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'users' => $users,
            'courses' => $courses,
            'disciplines' => $disciplines,
            'tasks' => $tasks,
            'files' => $files,
        ]);
    }

    public function resetCourseBackground(Request $request, Course $course): JsonResponse
    {
        if (!$this->isAdmin($request->user())) {
            return response()->json([
                'error' => 'Доступ разрешен только администратору.',
            ], 403);
        }

        if ($course->background_logo_id) {
            $file = File::find($course->background_logo_id);

            if ($file) {
                if (Storage::disk('public')->exists($file->path)) {
                    Storage::disk('public')->delete($file->path);
                }

                $file->delete();
            }
        }

        $course->forceFill(['background_logo_id' => null])->save();

        return response()->json([
            'message' => 'Баннер курса сброшен.',
            'course' => $course->fresh(['backgroundLogo']),
        ]);
    }

    private function isAdmin(?User $user): bool
    {
        return $user?->role?->name === Roles::ADMIN->value;
    }
}
