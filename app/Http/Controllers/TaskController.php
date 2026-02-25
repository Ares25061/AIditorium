<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTaskRequest;
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
            return response()->json(['error' => 'Tasks not found'], 404);
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
            return response()->json(['error' => 'Course not found'], 404);
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
        return response()->json(['message' => 'Task created!', 'task' => $task], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $task = Task::find($id);
        $course = Course::find($task->course_id);
        $this->authorize('view',$course);
        if (is_null($task)) {
            return response()->json(['error' => 'Task not found'], 404);
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
            return response()->json(['error' => 'Task not found'], 404);
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
        return response()->json(['message' => 'Task updated!', 'task' => $task], 200);
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
            return response()->json(['error' => 'Task not found'], 404);
        }
        $task->delete();
        return response()->json(['message' => 'Task deleted!'], 200);
    }

    public function viewTasks(int $course_id, Request $request)
    {
        $user = Auth::user();
        $course = Course::find($course_id);
        if (empty($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $this->authorize('view-mine', [Task::class,$course]);
        $tasks = $user->tasks()->where('course_id', $course_id)->paginate($request->per_page ?? 15);
        if ($tasks->isEmpty()) {
            return response()->json(['error' => 'Tasks not found'], 404);
        }
        return response()->json($tasks);
    }
}
