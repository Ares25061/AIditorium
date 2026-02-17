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
        return Response::deny('You do not have permission to view any files.');
    }
    public function view(User $user,File $file)
    {
        if ($user->hasPermission(CoursePermissions::VIEW) || $user->id === $file->user_id) {
            return Response::allow();
        }
        if ($file->is_public){
            return Response::allow();
        }
        return Response::deny("You don't have permission to view this file");

    }
    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::UPDATE)) {
            return Response::allow();
        }
        if ($user->courses()->where('course_id', $course->id)->first()->role === CourseUsersRoleEnum::STUDENT->value)
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
}
