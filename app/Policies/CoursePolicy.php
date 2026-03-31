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
        return Response::deny(__('policies.course.view_any.deny'));
    }

    public function view(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.view.deny'));
    }

    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::UPDATE)) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::deny(__('policies.course.update.not_enrolled'));
        }
        if ($user->courses()->where('course_id', $course->id)->first()->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.update.deny'));
    }

    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::DELETE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.delete.deny'));
    }

    public function hardDelete(User $user)
    {
        if ($user->hasPermission(CoursePermissions::HARD_DELETE)) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.hard_delete.deny'));
    }

    public function restore(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::RESTORE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.restore.deny'));
    }

    public function generateTeacherCodeInvite(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::GENERATE_TEACHER_CODE_INVITE)) {
            return Response::allow();
        }
        if ($course->is_closed) {
            return Response::deny(__('messages.course_closed'));
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.generate_teacher_code.deny'));
    }

    public function removeUser(User $user, Course $course, User $model)
    {
        if ($user->hasPermission(CoursePermissions::REMOVE_USER)) {
            return Response::allow();
        }
        if ($user->id === $model->id) {
            return Response::deny(__('policies.course.remove_user.cannot_remove_self'));
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.remove_user.deny'));
    }

    public function close(User $user, Course $course)
    {
        if ($course->is_closed) {
            return Response::deny(__('policies.course.close.already_closed'));
        }
        if ($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.close.deny'));
    }

    public function reopen(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if (!$course->is_closed) {
            return Response::deny(__('policies.course.reopen.already_open'));
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.reopen.deny'));
    }

    public function regenerateInviteCode(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::CLOSE)) {
            return Response::allow();
        }
        if ($course->is_closed) {
            return Response::deny(__('policies.course.regenerate_invite.course_closed'));
        }
        if ($user->id === $course->creator_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.course.regenerate_invite.deny'));
    }

    public function getUsers(User $user, Course $course)
    {
        if ($user->hasPermission(CoursePermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::allow();
        }
        if (empty($user->courses()->where('course_id', $course->id)->first())) {
            return Response::deny(__('policies.course.get_users.not_enrolled'));
        }
        return Response::deny(__('policies.course.get_users.deny'));
    }
}
