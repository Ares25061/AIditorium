<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    use AuthorizesRequests;


    public function index(Request $request)
    {
        $this->authorize('viewAny', File::class);
        $files = File::paginate($request->per_page ?? 15);
        if ($files->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])
            ],404);
        }
        return response()->json([
            'files' => $files
        ]);
    }


    public function courseFiles(Request $request, int $courseId)
    {
        $course = Course::find($courseId);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('viewAnyInCourse', [File::class, $course]);
        $files = File::where('course_id', $courseId)
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'email');
            }])
            ->paginate($request->per_page ?? 15);
        return response()->json([
            'course' => $course->only(['id', 'name', 'description', 'status']),
            'files' => $files
        ]);
    }


    public function studentFiles(Request $request, int $courseId, int $studentId)
    {
        $course = Course::find($courseId);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('viewStudentFiles', [File::class, $course]);
        $student = User::find($studentId);
        if (empty($student)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.student')])
            ], 404);
        }
        $isInCourse = $student->courses()
            ->where('course_id', $courseId)
            ->exists();
        if (empty($isInCourse)) {
            return response()->json([
                'error' => __('messages.not_enrolled')
            ]);
        }
        $files = File::where('course_id', $courseId)
            ->where('user_id', $studentId)
            ->paginate($request->per_page ?? 15);
        return response()->json([
            'course' => $course->only(['id', 'name']),
            'student' => $student->only(['id', 'name', 'email']),
            'files' => $files
        ]);
    }

    public function downloadStudentFile(int $courseId, int $studentId, int $fileId)
    {
        $course = Course::find($courseId);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $file = File::where('id', $fileId)
            ->where('course_id', $courseId)
            ->where('user_id', $studentId)
            ->first();
        if (empty($file)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])
            ], 404);
        }
        $this->authorize('view', $file);
        if (Storage::disk('public')->missing($file->path)) {
            return response()->json(['error' => __('messages.file_not_on_server')], 404);
        }
        return Storage::disk('public')->download($file->path);
    }



    public function store(CreateFileRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $path = $validated['file']->store($validated['type'] ?? 'another', 'public');
        $file = File::create([
            'path' => $path,
            'user_id' => $user->id,
            'type' => $validated['type'] ?? 'another',
            'course_id' => $validated['course_id'] ?? null,
            'task_id' => $validated['task_id'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
        ]);
        return response()->json(['message' => __('messages.created', ['item' => __('messages.items.file')]), 'file' => $file], 200);
    }


    public function show(int $id)
    {
        $file = File::find($id);
        $this->authorize('view',$file);
        if (is_null($file)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])], 404);
        }
        return response()->json(['file' => $file]);
    }


    public function update(UpdateFileRequest $request, int $id)
    {
        $this->authorize('update', File::class);
        $file = File::find($id);
        if (is_null($file)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])], 404);
        }
        $validated = $request->validated();
        if (!empty($validated['type'])) {
            Storage::move($file->path, $validated['type'] . '/' . basename($file->path));
            $file->path = $validated['type'] . '/' . basename($file->path);

        }
        $file->update([
            ...$validated,
        ]);
        return response()->json(['message' => __('messages.updated', ['item' => __('messages.items.file')])], 200);
    }


    public function destroy(int $id)
    {
        $file = File::find($id);
        $this->authorize('delete',$file);
        if (is_null($file)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])], 404);
        }
        if(Storage::exists($file->path)) {
            Storage::delete($file->path);
        }
        $file->delete();
        return response()->json(['message' => __('messages.deleted', ['item' => __('messages.items.file')])]);
    }

    public function download(int $id)
    {
        $file = File::find($id);
        if (is_null($file)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.file')])], 404);
        }
        $this->authorize('view', $file);
        if (Storage::missing($file->path)) {
            return response()->json(['error' => __('messages.file_not_on_server')], 404);
        }
        return Storage::download($file->path);
    }
}
