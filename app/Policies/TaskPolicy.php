<?php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\GradePermissions;
use App\Enums\StatusCourseEnum;
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
        if($course->status === StatusCourseEnum::ARCHIVED->value){
            return Response::deny(__('policies.task.create.archived'));
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
        if($course->status === StatusCourseEnum::ARCHIVED->value){
            return Response::deny(__('policies.task.update.archived'));
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
        if($course->status === StatusCourseEnum::ARCHIVED->value){
            return Response::deny(__('policies.task.delete.archived'));
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
    public function viewTasks(User $user, Course $course)
    {
        if ($user->courses()->where('course_id', $course->id)->first())
        {
            return Response::allow();
        }
        return Response::deny(__('messages.not_enrolled'));
    }

    public function submit(User $user, Course $course)
    {
        $userCourse = $user->courses()->where('course_id', $course->id)->first();
        return $userCourse && $userCourse->pivot->role === 'student';
    }

    public function viewSubmissions(User $user, Course $course)
    {
        $userCourse = $user->courses()->where('course_id', $course->id)->first();
        return $userCourse && $userCourse->pivot->role === 'teacher';
    }

    public function manageReviewProfile(User $user, Course $course): bool
    {
        if ($user->hasPermission(TaskPermissions::UPDATE)) {
            return true;
        }

        $userCourse = $user->courses()->where('course_id', $course->id)->first();

        return (bool) ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value);
    }

    public function runAiReview(User $user, Course $course): bool
    {
        return $this->manageReviewProfile($user, $course);
    }

    public function viewAiReviews(User $user, Course $course): bool
    {
        return $this->manageReviewProfile($user, $course);
    }

    public function applyAiReviewGrade(User $user, Course $course): bool
    {
        if ($user->hasPermission(GradePermissions::UPDATE)) {
            return true;
        }

        $userCourse = $user->courses()->where('course_id', $course->id)->first();

        return (bool) ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value);
    }
}
