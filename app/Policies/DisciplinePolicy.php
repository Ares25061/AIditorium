<?php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\DisciplinePermissions;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DisciplinePolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(DisciplinePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.discipline.view_any.deny'));
    }

    public function view(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::allow();
        }
        return Response::deny(__('policies.discipline.view.deny'));
    }

    public function create(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::CREATE)) {
            return Response::allow();
        }
        if($course->status = 'archived'){
            return Response::deny(__('policies.discipline.create.archived'));
        }
        if (empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }
        return Response::deny(__('policies.discipline.create.deny'));
    }

    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::UPDATE)) {
            return Response::allow();
        }
        if($course->status = 'archived'){
            return Response::deny(__('policies.discipline.update.archived'));
        }
        if (empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }
        return Response::deny(__('policies.discipline.update.deny'));
    }

    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::DELETE)) {
            return Response::allow();
        }
        if($course->status = 'archived'){
            return Response::deny(__('policies.discipline.delete.archived'));
        }
        if (empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }
        return Response::deny(__('policies.discipline.delete.deny'));
    }

    public function viewDisciplines(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        if ($user->courses()->where('course_id', $course->id)->first()) {
            return Response::allow();
        }
        return Response::deny(__('messages.not_enrolled'));
    }
}
