<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Task;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FileUploadService $fileUploadService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('view-any', Task::class);
        $tasks = Task::paginate($request->per_page ?? 15);
        if ($tasks->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        return response()->json([
            'tasks' => $tasks,
        ]);
    }

    public function store(CreateTaskRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }
        $this->authorize('create', [Task::class, $course]);
        $validated['user_id'] = $user->id;
        if (isset($validated['attachment'])) {
            $file = $this->fileUploadService->storeUploadedFile(
                $request->file('attachment'),
                [
                    'course_id' => $course->id,
                    'task_id' => null,
                    'user_id' => $user->id,
                    'type' => 'task',
                    'is_public' => true,
                ],
                'tasks',
                'public',
            );
            $validated['attachment_id'] = $file->id;
            unset($validated['attachment']);
        }
        if (array_key_exists('deadline', $validated)) {
            $validated['deadline'] = $this->normalizeDeadline($validated['deadline']);
        }
        $task = Task::create([
            ...$validated,
            'scores' => $request->scores ?? 100,
            'deadline' => $validated['deadline'] ?? $this->normalizeDeadline(Carbon::now()->addDay(7)),
        ]);
        return response()->json(['message' => __('messages.created', ['item' => __('messages.items.task')]), 'task' => $task], 200);
    }

    public function show(int $id)
    {
        $task = Task::find($id);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }

        $discipline = Discipline::find($task->discipline_id);
        $course = Course::find($discipline?->course_id ?? $task->course_id);
        $this->authorize('view', [Task::class, $course]);

        return response()->json(['task' => $task]);
    }

    public function showByNumber(string $courseIdentifier, string $disciplineIdentifier, int $number)
    {
        $course = $this->resolveCourse($courseIdentifier);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }
        $discipline = $this->resolveDiscipline($course->id, $disciplineIdentifier);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $task = Task::where('course_id', $course->id)
            ->where('discipline_id', $discipline->id)
            ->where('task_number', $number)
            ->first();
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        $this->authorize('view', [Task::class, $course]);
        return response()->json(['task' => $task]);
    }

    public function update(UpdateTaskRequest $request, int $id)
    {
        $user = Auth::user();
        $task = Task::find($id);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }

        $course = Course::find($task->course_id);
        $this->authorize('update', [Task::class, $course]);
        $validated = $request->validated();
        if (array_key_exists('deadline', $validated)) {
            $validated['deadline'] = $this->normalizeDeadline($validated['deadline']);
        }
        if (isset($validated['attachment'])) {
            if (!is_null($task->attachment_id)) {
                $oldFile = File::find($task->attachment_id);
                if (!is_null($oldFile) && Storage::disk('public')->exists($oldFile->path)) {
                    Storage::disk('public')->delete($oldFile->path);
                    $oldFile->delete();
                }
            }
            $file = $this->fileUploadService->storeUploadedFile(
                $request->file('attachment'),
                [
                    'course_id' => $course?->id,
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'type' => 'task',
                    'is_public' => true,
                ],
                'tasks',
                'public',
            );
            $validated['attachment_id'] = $file->id;
            unset($validated['attachment']);
        }
        $task->update([
            ...$validated,
        ]);
        return response()->json(['message' => __('messages.updated', ['item' => __('messages.items.task')]), 'task' => $task], 200);
    }

    public function destroy(int $id)
    {
        $task = Task::find($id);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }

        $course = Course::find($task->course_id);
        $this->authorize('delete', [Task::class, $course]);
        $task->delete();
        return response()->json(['message' => __('messages.deleted', ['item' => __('messages.items.task')])], 200);
    }

    public function viewTasks(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'discipline_id' => 'sometimes|integer|exists:disciplines,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:created_at,title,deadline',
            'sort_direction' => 'required_with:sort_by|in:asc,desc',
        ]);
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }
        $this->authorize('view-tasks', [Task::class, $course]);
        $query = Task::query()->where('course_id', $validated['course_id']);

        if (isset($validated['discipline_id'])) {
            $query->where('discipline_id', $validated['discipline_id']);
        }

        if (isset($validated['sort_by']) && isset($validated['sort_direction'])) {
            $sortBy = $validated['sort_by'] === 'title' ? 'name' : $validated['sort_by'];
            $query->orderBy($sortBy, $validated['sort_direction']);
        } else {
            $query->orderBy('created_at', 'desc');
        }
        $tasks = $query->paginate($validated['per_page'] ?? 15);
        return response()->json($tasks);
    }

    public function attachSubmission(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'task_id' => 'required|integer|exists:tasks,id',
            'file' => 'required|file|max:20480',
            'comment' => 'sometimes|string|max:1000',
        ]);

        $task = Task::find($validated['task_id']);
        $course = Course::find($task->course_id);
        $this->authorize('submit', [Task::class, $course]);

        $isEnrolled = $user->courses()->where('course_id', $course->id)->exists();
        if (!$isEnrolled) {
            return response()->json(['error' => __('messages.not_enrolled')], 403);
        }

        $file = $this->fileUploadService->storeUploadedFile(
            $request->file('file'),
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'task_id' => $task->id,
                'type' => 'submission',
                'is_public' => false,
            ],
            'submissions',
            'public',
        );

        return response()->json([
            'message' => __('messages.submitted', ['item' => __('messages.items.submission')]),
            'submission' => $file,
        ], 201);
    }

    public function detachSubmission(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'task_id' => 'required|integer|exists:tasks,id',
            'file_id' => 'required|integer|exists:files,id',
        ]);

        $task = Task::find($validated['task_id']);

        $file = File::where('id', $validated['file_id'])
            ->where('task_id', $validated['task_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$task || !$file) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.submission')])], 404);
        }

        $course = Course::find($task->course_id);
        $this->authorize('submit', [Task::class, $course]);

        if (Storage::disk('public')->exists($file->path)) {
            Storage::disk('public')->delete($file->path);
        }

        $file->delete();

        return response()->json([
            'message' => __('messages.removed', ['item' => __('messages.items.submission')]),
        ]);
    }

    public function submissions(Request $request)
    {
        $validated = $request->validate([
            'task_id' => 'required|integer|exists:tasks,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $task = Task::find($validated['task_id']);

        if (!$task) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }

        $course = Course::find($task->course_id);
        $this->authorize('view-submissions', [Task::class, $course]);

        $submissions = File::where('task_id', $validated['task_id'])
            ->where('type', 'submission')
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'email');
            }])
            ->paginate($request->per_page ?? 15);

        return response()->json(['submissions' => $submissions]);
    }

    private function resolveCourse(string $courseIdentifier): ?Course
    {
        if (ctype_digit($courseIdentifier)) {
            return Course::find((int) $courseIdentifier);
        }

        return Course::where('slug', $courseIdentifier)->first();
    }

    private function resolveDiscipline(int $courseId, string $disciplineIdentifier): ?Discipline
    {
        $query = Discipline::where('course_id', $courseId);

        if (ctype_digit($disciplineIdentifier)) {
            return (clone $query)->where('id', (int) $disciplineIdentifier)->first();
        }

        return (clone $query)->where('slug', $disciplineIdentifier)->first();
    }

    private function normalizeDeadline(mixed $deadline): string
    {
        return Carbon::parse($deadline)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
    }
}
