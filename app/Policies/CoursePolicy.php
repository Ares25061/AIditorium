<?php

namespace App\Policies;

use App\CoursePermissions;
use App\CourseUsersRoleEnum;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;


class CoursePolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(CoursePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny('You do not have permission to view any courses.');
    }
    public function view(User $user,Course $course)
    {
        if ($user->hasPermission(CoursePermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())){
            return Response::allow();
        }
        return Response::deny("You don't have permission to view this course");

    }
    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::UPDATE)) {
            return Response::allow();
        }
        if ($user->courses()->where('course_id', $course->id)->first()->role === CourseUsersRoleEnum::TEACHER->value)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to update course");
    }
    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::DELETE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to archive this course");
    }
    public function hardDelete(User $user)
    {
        if ($user->hasPermission(CoursePermissions::HARD_DELETE)) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to delete courses");
    }
    public function restore(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::RESTORE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to restore this course");
    }
    public function generateTeacherCodeInvite(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::GENERATE_TEACHER_CODE_INVITE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to generate teacher code invite");
    }
    public function removeUser(User $user, Course $course, User $model)
    {
        if ($user->hasPermission(CoursePermissions::REMOVE_USER)) {
            return Response::allow();
        }
        if ($user->id === $model->id)
        {
            return Response::deny("You can't remove yourself");
        }
        if ($user->id === $course->creator_id)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to remove user from this course");
    }
}
