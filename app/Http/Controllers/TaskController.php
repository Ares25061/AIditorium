<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\ViewMineTasksRequest;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view-any', Task::class);
        $tasks = Task::paginate($request->per_page ?? 15);
        if ($tasks->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        return response()->json([
            'tasks' => $tasks
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
        if(isset($validated['attachment'])){
            $path = $request->file('attachment')->store('tasks', 'public');
            $file = File::create([
                'path' => $path,
                'user_id' => $user->id,
                'type' => 'task',
                'is_public' => true,
            ]);
            $validated['attachment_id'] = $file->id;
            unset($validated['attachment']);
        }
        $task = Task::create([
            ...$validated,
            'scores' => $request->scores ?? 100,
            'deadline' => $request->deadline ?? Carbon::now()->addDay(7),
        ]);
        return response()->json(['message' => __('messages.created', ['item' => __('messages.items.task')]), 'task' => $task], 200);
    }


    public function show(int $id)
    {
        $task = Task::find($id);
        $discipline = Discipline::find($task->discipline_id);
        $course = Course::find($discipline->course_id);
        $this->authorize('view',[Task::class, $course]);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
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
        $this->authorize('view',[Task::class, $course]);
        return response()->json(['task' => $task]);
    }


    public function update(UpdateTaskRequest $request, int $id)
    {
        $user = Auth::user();
        $task = Task::find($id);
        $course = Course::find($task->course_id);
        $this->authorize('update', $course);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        $validated = $request->validated();
        if(isset($validated['attachment'])){
            if (!is_null($task->attachment_id)) {
                $oldFile = File::find($task->attachment_id);
                if (!is_null($oldFile) && Storage::exists($oldFile->path)) {
                    Storage::delete($oldFile->path);
                    $oldFile->delete();
                }
            }
            $path = $request->file('attachment')->store('tasks', 'public');
            $file = File::create([
                'path' => $path,
                'user_id' => $user->id,
                'type' => 'task',
                'is_public' => true,
            ]);
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
        $course = Course::find($task->course_id);
        $this->authorize('delete', [Task::class,$course]);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        $task->delete();
        return response()->json(['message' => __('messages.deleted', ['item' => __('messages.items.task')])], 200);
    }

    public function viewTasks(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'discipline_id' => 'sometimes|integer|exists:disciplines,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:created_at,title,deadline,status',
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
}
