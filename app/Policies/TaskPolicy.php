<?php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\TaskPermissions;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(TaskPermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.task.view_any.deny'));
    }
    public function view(User $user,Course $course)
    {
        if ($user->hasPermission(TaskPermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())){
            return Response::allow();
        }
        return Response::deny(__('policies.task.view.deny'));

    }
    public function create(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::CREATE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny(__('policies.task.create.deny'));
    }
    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::UPDATE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny(__('policies.task.update.deny'));
    }
    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::DELETE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny(__('messages.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny(__('policies.task.delete.deny'));
    }
    public function viewMine(User $user, Course $course)
    {
        if ($user->courses()->where('course_id', $course->id)->first())
        {
            return Response::allow();
        }
        return Response::deny(__('messages.not_enrolled'));
    }
}
