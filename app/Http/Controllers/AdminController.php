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

    private function isAdmin(?User $user): bool
    {
        return $user?->role?->name === Roles::ADMIN->value;
    }
}
