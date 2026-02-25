<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddUserToCourseRequest;
use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\RemoveUserFromCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('view-any', Course::class);
        $courses = Course::paginate($request->per_page ?? 15);
        if ($courses->isEmpty()) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        return response()->json([
            'courses' => $courses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCourseRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $validated['creator_id'] = $user->id;
        $validated['invite_code'] = Str::random(6);
        $course = Course::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);
        $user->courses()->attach($course->id, ['role' => 'teacher']);
        return response()->json([
            'message' => __('messages.created', ['item' => __('messages.items.course')]),
            'course' => $user->courses()->where('course_id', $course->id)->first()
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $course = Course::find($id);
        $this->authorize('view',$course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        return response()->json(['course' => $course]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, int $id)
    {
        $course = Course::find($id);
        $this->authorize('update', $course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $validated = $request->validated();
        $course->update([
            ...$validated,
        ]);
        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.course')]),
            'course' => $course
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function archive(int $id)
    {
        $course = Course::find($id);
        $this->authorize('delete',$course);

        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $course->update([
            'status' => 'archived',
        ]);
        return response()->json([
            'message' => __('messages.archived', ['item' => __('messages.items.course')]),
            'course' => $course
        ], 200);
    }

    public function destroy(int $id)
    {
        $course = Course::find($id);
        $this->authorize('hard-delete',$course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $course->delete();
        return response()->json([
            'message' => __('messages.deleted', ['item' => __('messages.items.course')])
        ], 200);
    }

    public function restore(int $id)
    {
        $course = Course::find($id);
        $this->authorize('restore',$course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $course->update([
            'status' => 'active',
        ]);
        return response()->json([
            'message' => __('messages.restored', ['item' => __('messages.items.course')]),
            'course' => $course
        ], 200);
    }
    public function generateTeacherCodeInvite(int $id)
    {
        $course = Course::find($id);
        $this->authorize('generate-teacher-code-invite',$course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $course->update(['invite_code_teacher' => Str::random(9)]);
        return response()->json([
            'message' => __('messages.invite_code_generated'),
            'course' => $course
        ], 200);
    }
    public function addUserToCourse(AddUserToCourseRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::where('invite_code',$validated['code'])
            ->orWhere('invite_code_teacher',$validated['code'])
            ->first();
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.invalid_invite_code')
            ], 404);
        }
        if ($user->courses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'error' => __('messages.already_enrolled'),
                'user_id' => $user->id,
                'course_id' => $course->id
            ], 409);
        }
        if ($validated['code'] == $course->invite_code) {
            $user->courses()->syncWithoutDetaching($course->id);
        }
        else if ($validated['code'] == $course->invite_code_teacher) {
            $user->courses()->syncWithoutDetaching([
                $course->id => ['role' => 'teacher']
            ]);
        }
        return response()->json([
            'message' => __('messages.added', ['item' => __('messages.items.user')]),
            'user_courses' => $user->courses()->where('course_id', $course->id)->first()
        ], 200);
    }

    public function removeUser(int $id, RemoveUserFromCourseRequest $request)
    {
        $course = Course::find($id);
        $validated = $request->validated();
        $model = User::find($validated['user_id']);
        $this->authorize('remove-user',[$course, $model]);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        if ($model->courses()->where('course_id', $course->id)->doesntExist()) {
            return response()->json([
                'error' => __('messages.not_enrolled'),
                'user_id' => $model->id,
                'course_id' => $course->id
            ], 409);
        }
        $model->courses()->detach($course->id);
        return response()->json([
            'message' => __('messages.removed', ['item' => __('messages.items.user')])
        ], 200);
    }

    public function viewMine(Request $request)
    {
        $user = Auth::user();
        $courses = $user
            ->courses()
            ->paginate($request->per_page ?? 15);
        if ($courses->isEmpty()) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        return response()->json(['courses' => $courses]);
    }
}
