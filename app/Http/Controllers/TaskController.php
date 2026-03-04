<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\ViewMineTasksRequest;
use App\Models\Course;
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
    /**
     * Display a listing of the resource.
     */
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

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Display the specified resource.
     */
    public function show(ShowTaskRequest $request, int $taskId)
    {
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        $task = Task::find($taskId)->where($course->id === $validated['course_id'])->first();
        $this->authorize('view',$course);
        if (is_null($task)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        return response()->json(['task' => $task]);
    }

    /**
     * Update the specified resource in storage.
     */
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

    /**
     * Remove the specified resource from storage.
     */
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

    public function viewTasks(ViewMineTasksRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }
        $this->authorize('view-mine', [Task::class,$course]);
        $tasks = $user->tasks()->where('course_id', $validated['course_id'])
            ->paginate($validated['per_page'] ?? 15);
        if ($tasks->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }
        return response()->json($tasks);
    }
}
