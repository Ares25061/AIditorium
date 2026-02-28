<?php

namespace App\Policies;

use App\Enums\CoursePermissions;
use App\Enums\CourseUsersRoleEnum;
use App\Models\Course;
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
        if (empty($user->courses()->where('course_id', $course->id)->first()))
        {
            return Response::deny("You don't enrolled in this course");
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value)
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
        if ($course->is_closed) {
            return Response::deny("Course is closed");
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
    public function close(User $user, Course $course)
    {
        if ($course->is_closed) {
            return Response::deny("Course already closed");
        }
        if($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if($user->id === $course->creator_id)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to close this course");
    }
    public function reopen(User $user, Course $course)
    {
        if($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if (!$course->is_closed) {
            return Response::deny("Course already open");
        }
        if($user->id === $course->creator_id)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to reopen this course");
    }
    public function regenerateInviteCode(User $user, Course $course)
    {
        if($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if ($course->is_closed) {
            return Response::deny("Course is closed");
        }
        if($user->id === $course->creator_id)
        {
            return Response::allow();
        }
        return Response::deny("You don't have permission to regenerate invite code");
    }
}
