<?php

namespace App\Policies;

use App\CourseUsersRoleEnum;
use App\Models\Course;
use App\Models\User;
use App\TaskPermissions;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(TaskPermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny('You do not have permission to view any tasks.');
    }
    public function view(User $user,Course $course)
    {
        if ($user->hasPermission(TaskPermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())){
            return Response::allow();
        }
        return Response::deny("You don't have permission to view this task in course");

    }
    public function create(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::CREATE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny("You don't enrolled in this course");
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to create tasks in this course");
    }
    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::UPDATE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny("You don't enrolled in this course");
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to update tasks in this course");
    }
    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(TaskPermissions::DELETE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny("You don't enrolled in this course");
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to destroy this task in this course");
    }
    public function viewMine(User $user, Course $course)
    {
        if ($user->courses()->where('course_id', $course->id)->first())
        {
            return Response::allow();
        }
        return Response::deny("You don't enrolled in this course");
    }
}
