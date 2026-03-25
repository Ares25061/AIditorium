<?php
// app/Http/Controllers/GradeController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Models\Grade;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    use AuthorizesRequests;


    public function index(Request $request)
    {
        $this->authorize('viewAny', Grade::class);
        $grades = Grade::with(['student', 'course', 'task', 'discipline', 'grader'])
            ->paginate($request->per_page ?? 15);

        if ($grades->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.grade')])], 404);
        }

        return response()->json(['grades' => $grades]);
    }


    public function store(CreateGradeRequest $request)
    {
        $teacher = Auth::user();
        $validated = $request->validated();


        $student = User::find($validated['user_id']);
        if (!$student) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }


        $course = Course::find($validated['course_id']);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }


        $userCourse = $teacher->courses()
            ->where('course_id', $course->id)
            ->first();

        if (!$userCourse || $userCourse->pivot->role !== 'teacher') {
            return response()->json(['error' => __('messages.unauthorized')], 403);
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


        $existingGrade = Grade::where('user_id', $validated['user_id'])
            ->where('course_id', $validated['course_id'])
            ->when(isset($validated['task_id']), function($query) use ($validated) {
                return $query->where('task_id', $validated['task_id']);
            })
            ->first();

        if ($existingGrade) {
            return response()->json(['error' => __('messages.grade_exists')], 409);
        }

        $grade = Grade::create([
            ...$validated,
            'type' => 'teacher',
            'graded_by' => $teacher->id,
            'graded_at' => now(),
        ]);

        return response()->json([
            'message' => __('messages.created', ['item' => __('messages.items.grade')]),
            'grade' => $grade->load(['student', 'grader'])
        ], 201);
    }


    public function show(int $id)
    {
        $grade = Grade::with(['student', 'course', 'task', 'discipline', 'file', 'grader'])->find($id);

        if (!$grade) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.grade')])], 404);
        }

        $this->authorize('view', $grade);

        return response()->json(['grade' => $grade]);
    }


    public function update(UpdateGradeRequest $request, int $id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.grade')])], 404);
        }

        $this->authorize('update', $grade);

        $validated = $request->validated();

        $grade->update([
            ...$validated,
            'graded_by' => Auth::id(),
            'graded_at' => now(),
        ]);

        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.grade')]),
            'grade' => $grade
        ]);
    }


    public function destroy(int $id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.grade')])], 404);
        }

        $this->authorize('delete', $grade);

        $grade->delete();

        return response()->json([
            'message' => __('messages.deleted', ['item' => __('messages.items.grade')])
        ]);
    }

// for teachers
    public function courseGrades(Request $request, int $courseId)
    {
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $this->authorize('viewAnyInCourse', [Grade::class, $course]);

        $grades = Grade::where('course_id', $courseId)
            ->with(['student', 'task', 'discipline', 'grader'])
            ->paginate($request->per_page ?? 15);

        return response()->json($grades);
    }

// for students
    public function myGrades(Request $request, int $courseId)
    {
        $user = Auth::user();
        $course = Course::find($courseId);

        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $isEnrolled = $user->courses()->where('course_id', $courseId)->exists();
        if (!$isEnrolled) {
            return response()->json(['error' => __('messages.not_enrolled')], 403);
        }

        $grades = Grade::where('course_id', $courseId)
            ->where('user_id', $user->id)
            ->with(['task', 'discipline', 'grader'])
            ->paginate($request->per_page ?? 15);

        return response()->json($grades);
    }


    public function studentGrades(Request $request, int $courseId, int $studentId)
    {
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $student = User::find($studentId);
        if (!$student) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.user')])], 404);
        }

        $isEnrolled = $student->courses()->where('course_id', $courseId)->exists();
        if (!$isEnrolled) {
            return response()->json(['error' => __('messages.not_enrolled')], 404);
        }

        $this->authorize('viewStudentGrades', [Grade::class, $course, $student]);

        $grades = Grade::where('course_id', $courseId)
            ->where('user_id', $studentId)
            ->with(['task', 'discipline', 'grader'])
            ->paginate($request->per_page ?? 15);

        return response()->json($grades);
    }


    public function statistics(int $courseId)
    {
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }

        $this->authorize('viewStatistics', [Grade::class, $course]);

        $grades = Grade::where('course_id', $courseId);

        $stats = [
            'total_grades' => $grades->count(),
            'average_grade' => round($grades->avg('grade'), 2),
            'min_grade' => $grades->min('grade'),
            'max_grade' => $grades->max('grade'),
            'grades_by_task' => $grades->with('task')
                ->get()
                ->groupBy('task_id')
                ->map(function ($group) {
                    return [
                        'task_name' => $group->first()->task->title ?? 'Unknown',
                        'count' => $group->count(),
                        'average' => round($group->avg('grade'), 2),
                        'min' => $group->min('grade'),
                        'max' => $group->max('grade'),
                    ];
                }),
            'grades_distribution' => [
                'excellent' => $grades->where('grade', '>=', 90)->count(),
                'good' => $grades->whereBetween('grade', [75, 89])->count(),
                'satisfactory' => $grades->whereBetween('grade', [60, 74])->count(),
                'unsatisfactory' => $grades->where('grade', '<', 60)->count(),
            ]
        ];

        return response()->json($stats);
    }
}
