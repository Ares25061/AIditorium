<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDisciplineRequest;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\ShowTaskRequest;
use App\Http\Requests\UpdateDisciplineRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\ViewMineTasksRequest;
use App\Models\Course;
use App\Models\Discipline;
use App\Models\File;
use App\Support\SlugHelper;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class DisciplineController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('view-any', Discipline::class);
        $disciplines = Discipline::paginate($request->per_page ?? 15);
        if ($disciplines->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        return response()->json([
            'disciplines' => $disciplines
        ]);
    }


    public function store(CreateDisciplineRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('create', [Discipline::class, $course]);
        $validated['created_by'] = $user->id;
        if(isset($validated['slug'])){
            $validated['slug'] = SlugHelper::normalize($validated['slug']);
            if($validated['slug'] === ''){
                $validated['slug'] = null;
            }
            if(is_null($validated['slug'])){
                unset($validated['slug']);
            }
            else{
                if(!SlugHelper::containsLetters($validated['slug'])){
                    return response()->json(['error' => __("messages.slug_letters")], 409);
                }
                $existingDiscipline = Discipline::where('slug', $validated['slug'])
                    ->where('course_id', $validated['course_id']);
                if($existingDiscipline->exists()){
                    return response()->json(['error' => __("messages.slug_exists_in_course")], 409);
                }
            }
        }
        $discipline = Discipline::create([
            ...$validated,

        ]);
        return response()->json(['message' => __('messages.created', ['item' => __('messages.items.discipline')]), 'discipline' => $discipline], 200);
    }


    public function show(int $id)
    {
        $discipline = Discipline::find($id);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $course = Course::find($discipline->course_id);
        $this->authorize('view',[Discipline::class, $course]);
        return response()->json(['discipline' => $discipline]);
    }
    public function showBySlug(string $courseIdentifier, string $disciplineIdentifier)
    {
        $course = $this->resolveCourse($courseIdentifier);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])], 404);
        }
        $discipline = $this->resolveDiscipline($course->id, $disciplineIdentifier);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $this->authorize('view',[Discipline::class, $course]);
        return response()->json(['discipline' => $discipline]);
    }


    public function update(UpdateDisciplineRequest $request, int $id)
    {
        $discipline = Discipline::find($id);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $validated = $request->validated();
        $course = Course::find($discipline->course_id);
        $this->authorize('update', [Discipline::class, $course]);
        if(isset($validated['slug'])){
            $validated['slug'] = SlugHelper::normalize($validated['slug']);
            if($validated['slug'] === ''){
                $validated['slug'] = null;
            }
            if(is_null($validated['slug'])){
                $validated['slug'] = null;
            }
            else{
                if(!SlugHelper::containsLetters($validated['slug'])){
                    return response()->json(['error' => __("messages.slug_letters")], 409);
                }
                $existingDiscipline = Discipline::where('slug', $validated['slug'])
                    ->where('course_id', $discipline->course_id)
                    ->where('id', '!=', $id);
                if($existingDiscipline->exists()){
                    return response()->json(['error' => __("messages.slug_exists_in_course")], 409);
                }
            }
        }
        $discipline->update([
            ...$validated,
        ]);
        return response()->json(['message' => __('messages.updated', ['item' => __('messages.items.discipline')]), 'discipline' => $discipline], 200);
    }


    public function destroy(int $id)
    {
        $discipline = Discipline::find($id);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $course = Course::find($discipline->course_id);
        $this->authorize('delete', [Discipline::class,$course]);
        $discipline->delete();
        return response()->json(['message' => __('messages.deleted', ['item' => __('messages.items.discipline')])], 200);
    }


    public function viewDisciplines(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('view-disciplines', [Discipline::class, $course]);
        $disciplines = Discipline::withCount('tasks')
            ->where('course_id', $validated['course_id'])
            ->paginate($validated['per_page'] ?? 15);
        if ($disciplines->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        return response()->json($disciplines);
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
