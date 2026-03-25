<?php
// app/Http/Controllers/CommentController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\Task;
use App\Models\File;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    use AuthorizesRequests;


    public function index(Request $request)
    {
        $this->authorize('viewAny', Comment::class);
        $comments = Comment::with(['user', 'course', 'task'])
            ->paginate($request->per_page ?? 15);

        if ($comments->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
        }

        return response()->json(['comments' => $comments]);
    }


    public function store(CreateCommentRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();


        if (isset($validated['parent_id'])) {
            $parent = Comment::find($validated['parent_id']);
            if (!$parent) {
                return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
            }

            $validated['course_id'] = $parent->course_id;
        }


        if (isset($validated['course_id'])) {
            $course = Course::find($validated['course_id']);
            if (!$course) {
                return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
            }
            $this->authorize('create', [Comment::class, $course]);
        }

        if (isset($validated['task_id'])) {
            $task = Task::find($validated['task_id']);
            if (!$task) {
                return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
            }
        }

        if (isset($validated['discipline_id'])) {
            $discipline = Discipline::find($validated['discipline_id']);
            if (!$discipline) {
                return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
            }
        }

        if (isset($validated['file_id'])) {
            $file = File::find($validated['file_id']);
            if (!$file) {
                return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])], 404);
            }
        }

        $comment = Comment::create([
            ...$validated,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => __('messages.created', ['item' => __('messages.items.comment')]),
            'comment' => $comment->load('user')
        ], 201);
    }


    public function show(int $id)
    {
        $comment = Comment::with(['user', 'replies.user', 'parent'])->find($id);

        if (!$comment) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
        }

        $this->authorize('view', $comment);

        return response()->json(['comment' => $comment]);
    }

    public function update(UpdateCommentRequest $request, int $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
        }

        $this->authorize('update', $comment);

        $validated = $request->validated();
        $comment->update([
            ...$validated,
            'is_edited' => true,
        ]);

        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.comment')]),
            'comment' => $comment
        ]);
    }

    public function destroy(int $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
        }

        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json([
            'message' => __('messages.deleted', ['item' => __('messages.items.comment')])
        ]);
    }

    public function courseComments(Request $request, int $courseId)
    {
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $this->authorize('viewAnyInCourse', [Comment::class, $course]);

        $comments = Comment::where('course_id', $courseId)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->paginate($request->per_page ?? 15);

        return response()->json($comments);
    }

    public function taskComments(Request $request, int $taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.task')])], 404);
        }

        $this->authorize('view', $task);

        $comments = Comment::where('task_id', $taskId)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->paginate($request->per_page ?? 15);

        return response()->json($comments);
    }

    public function myComments(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:created_at,updated_at',
            'sort_direction' => 'required_with:sort_by|in:asc,desc',
        ]);

        $course = Course::find($validated['course_id']);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $this->authorize('view', $course);

        $query = Comment::where('user_id', $user->id)
            ->where('course_id', $validated['course_id']);

        if (isset($validated['sort_by']) && isset($validated['sort_direction'])) {
            $query->orderBy($validated['sort_by'], $validated['sort_direction']);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $comments = $query->with(['task', 'discipline', 'file'])
            ->paginate($validated['per_page'] ?? 15);

        return response()->json($comments);
    }

    public function replies(int $id)
    {
        $comment = Comment::find($id);

        if (!$comment) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.comment')])], 404);
        }

        $this->authorize('view', $comment);

        $replies = Comment::where('parent_id', $id)
            ->with('user')
            ->get();

        return response()->json(['replies' => $replies]);
    }
}
