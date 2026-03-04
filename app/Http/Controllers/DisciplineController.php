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
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DisciplineController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
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

    /**
     * Store a newly created resource in storage.
     */
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
        $discipline = Discipline::create([
            ...$validated,

        ]);
        return response()->json(['message' => __('messages.created', ['item' => __('messages.items.discipline')]), 'discipline' => $discipline], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(ShowTaskRequest $request,int $id)
    {
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        $discipline = Discipline::find($id);
        $this->authorize('view',[Discipline::class, $course]);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        return response()->json(['discipline' => $discipline]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDisciplineRequest $request, int $id)
    {
        $discipline = Discipline::find($id);
        $validated = $request->validated();
        $course = Course::find($discipline->course_id);
        $this->authorize('update', [Discipline::class, $course]);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $discipline->update([
            ...$validated,
        ]);
        return response()->json(['message' => __('messages.updated', ['item' => __('messages.items.discipline')])], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $discipline = Discipline::find($id);
        $course = Course::find($discipline->course_id);
        $this->authorize('delete', [Discipline::class,$course]);
        if (is_null($discipline)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        $discipline->delete();
        return response()->json(['message' => __('messages.deleted', ['item' => __('messages.items.discipline')])], 200);
    }

    public function viewDisciplines(ViewMineTasksRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $course = Course::find($validated['course_id']);
        if (empty($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('view-disciplines', [Discipline::class,$course]);
        $disciplines = $user->disciplines()->where('course_id', $validated['course_id'])
            ->paginate($validated['per_page'] ?? 15);
        if ($disciplines->isEmpty()) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.discipline')])], 404);
        }
        return response()->json($disciplines);
    }
}
