<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddUserToCourseRequest;
use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\RemoveUserFromCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    use AuthorizesRequests;


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


    public function store(CreateCourseRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();
        $validated['creator_id'] = $user->id;
        $validated['invite_code'] = Str::random(6);
        if(isset($validated['background_logo'])){
            $path = $request->file('background_logo')->store('backs', 'public');
            $file = File::create([
                'path' => $path,
                'user_id' => $user->id,
                'type' => 'background',
                'is_public' => true,
            ]);
            $validated['background_logo_id'] = $file->id;
            unset($validated['background_logo']);
        }
        if(isset($validated['slug'])){
            $validated['slug'] = Str::slug($validated['slug']);
            if(Course::where('slug', $validated['slug'])->exists()){
                return response()->json(['error' => __("messages.slug_exists")], 409);
            }
            else if(empty($validated['slug'])){
                return response()->json(['error' => __("messages.slug_letters")], 409);
            }
        }
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


    public function update(UpdateCourseRequest $request, int $id)
    {
        $user = Auth::user();
        $course = Course::find($id);
        $this->authorize('update', $course);
        if (is_null($course)) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $validated = $request->validated();
        if(isset($validated['background_logo'])){
            if (!is_null($course->background_logo_id)) {
                $oldFile = File::find($course->background_logo_id);
                if (!is_null($oldFile) && Storage::exists($oldFile->path)) {
                    Storage::delete($oldFile->path);
                    $oldFile->delete();
                }
            }
            $path = $request->file('background_logo')->store('backs', 'public');
            $file = File::create([
                'path' => $path,
                'user_id' => $user->id,
                'type' => 'background',
                'is_public' => true,
            ]);
            $validated['background_logo_id'] = $file->id;
            unset($validated['background_logo']);
        }
        if(isset($validated['slug'])){
            $validated['slug'] = Str::slug($validated['slug']);
            if(Course::where('slug', $validated['slug'])->exists()){
                return response()->json(['error' => __("messages.slug_exists")], 409);
            }
            else if(empty($validated['slug'])){
                return response()->json(['error' => __("messages.slug_letters")], 409);
            }
        }
        $course->update([
            ...$validated,
        ]);
        return response()->json([
            'message' => __('messages.updated', ['item' => __('messages.items.course')]),
            'course' => $course
        ], 200);
    }


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


    public function addUser(AddUserToCourseRequest $request)
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
        if($course->is_closed)
        {
            return response()->json(['error' => __('messages.course_closed'), 'course'=> $course], 409);
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
            ->withCount('disciplines')
            ->paginate($request->per_page ?? 15);
        if ($courses->isEmpty()) {
            return response()->json([
                'error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        return response()->json([
            'courses' => $courses,
            'total_courses' => $courses->total()
        ]);
    }


    public function leave(int $courseId)
    {
        $user = Auth::user();
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        if ($user->courses()->where('course_id', $course->id)->doesntExist()) {
            return response()->json([
                'error' => __('messages.not_enrolled'),
                'user_id' => $user->id,
                'course_id' => $course->id
            ], 409);
        }
        if($course->status = 'archived'){
            return response()->json(['message' => __('messages.course_archived'), 'course' => $course], 406);
        }
        $user->courses()->detach($course->id);
        if ($user->id === $course->creator_id)
        {
            $course->update(['status' => 'archived']);
            return response()->json(['message' => __('messages.course_left'), 'course' => $course->only('status')], 200);
        }
        return response()->json(['message' => __('messages.course_left')], 200);
    }


    public function close(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('close',$course);
        $course->update(['is_closed' => 1]);
        return response()->json(['message' => __('messages.course_closed'), 'course' => $course], 200);
    }


    public function reopen(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('reopen',$course);

        $course->update(['is_closed' => 0]);
        return response()->json(['message' => __('messages.reopened', ['item' => __('messages.items.course')]),  'course' => $course], 200);
    }


    public function regenerateInviteCode(int $courseId)
    {
        $course = Course::find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])
            ], 404);
        }
        $this->authorize('regenerate-invite-code',$course);
        $course->update(['invite_code' => Str::random(6)]);
        return response()->json(['message' => __('messages.invite_code_regenerated'), 'course' => $course], 200);
    }

    public function getUsers(int $courseId)
    {
        $course = Course::withCount('users')->find($courseId);
        if (is_null($course)) {
            return response()->json(['error' => __('messages.not_found', ['item' => __('messages.items.course')])]);
        }
        $this->authorize('get-users',$course);
        $users = $course->users;
        $usersCount = $course->users_count;
        return response()->json(['users' => $users, 'usersCount' => $usersCount], 200);
    }
}
