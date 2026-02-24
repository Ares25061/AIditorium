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
            return response()->json(['error' => 'Courses not found'], 404);
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
        return response()->json(['message' => 'Course created!', 'course' => $user->courses()->where('course_id', $course->id)->first()], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $course = Course::find($id);
        $this->authorize('view',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
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
            return response()->json(['error' => 'Course not found'], 404);
        }
        $validated = $request->validated();
        $course->update([
            ...$validated,
        ]);
        return response()->json(['message' => 'Course updated!', 'course' => $course], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function archive(int $id)
    {
        $course = Course::find($id);
        $this->authorize('delete',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->update([
            'status' => 'archived',
        ]);
        return response()->json(['message' => 'Course archived!', 'course' => $course], 200);
    }
    public function destroy(int $id)
    {
        $course = Course::find($id);
        $this->authorize('hard-delete',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->delete();
        return response()->json(['message' => 'Course deleted!'], 200);
    }

    public function restore(int $id)
    {
        $course = Course::find($id);
        $this->authorize('restore',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->update([
            'status' => 'active',
        ]);
        return response()->json(['message' => 'Course is active!', 'course' => $course], 200);
    }
    public function generateTeacherCodeInvite(int $id)
    {
        $course = Course::find($id);
        $this->authorize('generate-teacher-code-invite',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->update(['invite_code_teacher' => Str::random(9)]);
        return response()->json(['message' => 'Course invite code for teacher generated!', 'course' => $course], 200);
    }
    public function addUser(AddUserToCourseRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::where('invite_code',$validated['code'])
            ->orWhere('invite_code_teacher',$validated['code'])
            ->first();
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        if ($user->courses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'error' => 'User is already enrolled in this course',
                'user_id' => $user->id,
                'course_id' => $course->id
            ], 409);
        }
        if($course->is_closed)
        {
            return response()->json(['error' => 'Course is closed', 'course'=> $course], 409);
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
            'message' => 'User added to course!',
            'user_courses' => $user->courses()
                ->where('course_id', $course->id)
                ->first()
        ], 200);
    }

    public function removeUser(int $id, RemoveUserFromCourseRequest $request)
    {
        $course = Course::find($id);
        $validated = $request->validated();
        $model = User::find($validated['user_id']);
        $this->authorize('remove-user',[$course, $model]);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        if ($model->courses()->where('course_id', $course->id)->doesntExist()) {
            return response()->json([
                'error' => 'User is not enrolled in this course',
                'user_id' => $model->id,
                'course_id' => $course->id
            ], 409);
        }
        $model->courses()->detach($course->id);
        return response()->json(['message' => 'User removed from course!'], 200);
    }

    public function viewMine(Request $request)
    {
        $user = Auth::user();
        $courses = $user
            ->courses()
            ->paginate($request->per_page ?? 15);
        if ($courses->isEmpty()) {
            return response()->json(['error' => 'Courses not found'], 404);
        }
        return response()->json(['courses' => $courses]);
    }
    public function leave(int $courseId)
    {
        $user = Auth::user();
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        if ($user->courses()->where('course_id', $course->id)->doesntExist()) {
            return response()->json([
                'error' => 'User is not enrolled in this course',
                'user_id' => $user->id,
                'course_id' => $course->id
            ], 409);
        }
        if($course->status = 'archived'){
            return response()->json(['message' => 'Course is archived, you cant leave from archived course', 'course' => $course], 406);
        }
        $user->courses()->detach($course->id);
        if ($user->id === $course->creator_id)
        {
            $course->update(['status' => 'archived']);
            return response()->json(['message' => 'Course leaved!', 'course' => $course->only('status')], 200);
        }
        return response()->json(['message' => 'Course leaved!'], 200);
    }
    public function close(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $this->authorize('close',$course);
        $course->update(['is_closed' => 1]);
        return response()->json(['message' => 'Course is closed!', 'course' => $course], 200);
    }
    public function reopen(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $this->authorize('reopen',$course);

        $course->update(['is_closed' => 0]);
        return response()->json(['message' => 'Course is reopen!', 'course' => $course], 200);
    }
    public function regenerateInviteCode(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $this->authorize('regenerate-invite-code',$course);
        $course->update(['invite_code' => Str::random(6)]);
        return response()->json(['message' => 'Course  invite code regenerated!', 'course' => $course], 200);
    }
}
