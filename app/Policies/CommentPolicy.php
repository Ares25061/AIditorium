<?php
// app/Policies/CommentPolicy.php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\CommentPermissions;
use App\Models\Comment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(CommentPermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.comment.view_any.deny'));
    }

    public function view(User $user, Comment $comment)
    {
        // Global permission
        if ($user->hasPermission(CommentPermissions::VIEW)) {
            return Response::allow();
        }

        // Own comment
        if ($user->id === $comment->user_id) {
            return Response::allow();
        }

        // Teacher in the course can view all comments
        if ($comment->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $comment->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                return Response::allow();
            }
        }

        return Response::deny(__('policies.comment.view.deny'));
    }

    public function create(User $user, ?Course $course = null)
    {
        // If creating comment in a course, user must be enrolled
        if ($course) {
            $isEnrolled = $user->courses()
                ->where('course_id', $course->id)
                ->exists();

            if (!$isEnrolled) {
                return Response::deny(__('policies.comment.create.not_enrolled'));
            }
        }

        return Response::allow();
    }

    public function update(User $user, Comment $comment)
    {
        // Only the author can update their own comment
        if ($user->id === $comment->user_id) {
            return Response::allow();
        }

        // Teachers can update any comment in their course
        if ($comment->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $comment->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                return Response::allow();
            }
        }

        return Response::deny(__('policies.comment.update.deny'));
    }

    public function delete(User $user, Comment $comment)
    {
        // Global permission
        if ($user->hasPermission(CommentPermissions::DELETE)) {
            return Response::allow();
        }

        // Own comment
        if ($user->id === $comment->user_id) {
            return Response::allow();
        }

        // Teachers can delete any comment in their course
        if ($comment->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $comment->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                return Response::allow();
            }
        }

        return Response::deny(__('policies.comment.delete.deny'));
    }

    public function viewAnyInCourse(User $user, Course $course)
    {
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny(__('policies.comment.view_any_in_course.deny'));
    }
}
