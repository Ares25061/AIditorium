<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
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
        $this->authorize('viewAny', Course::class);
        $courses = Course::paginate($request->per_page ?? 15);
        return response()->json([
            'files' => $courses
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
        return response()->json(['message' => 'Course created!', 'course' => $course], 200);
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
        $this->authorize('update', Course::class);
        $course = Course::find($id);
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
    public function destroy(int $id)
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
    public function hardDestroy(int $id)
    {
        $course = Course::find($id);
        $this->authorize('hard-delete',$course);
        if (is_null($course)) {
            return response()->json(['error' => 'Course not found'], 404);
        }
        $course->delete();
        return response()->json(['message' => 'Course deleted!'], 200);
    }
}
