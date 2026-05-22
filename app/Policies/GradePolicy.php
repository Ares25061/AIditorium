<?php
// app/Policies/GradePolicy.php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\GradePermissions;
use App\Enums\TaskPermissions;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GradePolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(GradePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.grade.view_any.deny'));
    }

    public function view(User $user, Grade $grade)
    {

        if ($user->hasPermission(GradePermissions::VIEW)) {
            return Response::allow();
        }

        if ($user->id === $grade->user_id) {
            return Response::allow();
        }

        if ($grade->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $grade->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                if (!$grade->task_id || $this->canReviewTask($user, $grade->task)) {
                    return Response::allow();
                }
            }
        }

        return Response::deny(__('policies.grade.view.deny'));
    }

    public function create(User $user, Course $course)
    {
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.create.deny'));
    }

    public function update(User $user, Grade $grade)
    {
        if ($grade->task_id && $this->canReviewTask($user, $grade->task)) {
            return Response::allow();
        }

        if (!$grade->task_id && $this->isCourseTeacher($user, (int) $grade->course_id)) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.update.deny'));
    }

    public function delete(User $user, Grade $grade)
    {
        // Global permission
        if ($user->hasPermission(GradePermissions::DELETE)) {
            return Response::allow();
        }

        if ($grade->task_id && $this->canReviewTask($user, $grade->task)) {
            return Response::allow();
        }

        if (!$grade->task_id && $this->isCourseTeacher($user, (int) $grade->course_id)) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.delete.deny'));
    }

    public function viewAnyInCourse(User $user, Course $course)
    {

        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.view_any_in_course.deny'));
    }

    public function viewStudentGrades(User $user, Course $course, User $student)
    {
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        if ($user->id === $student->id) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.view_student_grades.deny'));
    }

    public function viewStatistics(User $user, Course $course)
    {

        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny(__('policies.grade.view_statistics.deny'));
    }

    private function canReviewTask(User $user, ?Task $task): bool
    {
        if (!$task) {
            return false;
        }

        if ($user->hasPermission(TaskPermissions::REVIEW_SUBMISSIONS)) {
            return true;
        }

        if (!$this->isCourseTeacher($user, (int) $task->course_id)) {
            return false;
        }

        if ((int) $task->user_id === (int) $user->id) {
            return true;
        }

        return $task->reviewers()
            ->whereKey($user->id)
            ->exists();
    }

    private function isCourseTeacher(User $user, int $courseId): bool
    {
        $userCourse = $user->courses()
            ->where('course_id', $courseId)
            ->first();

        return (bool) ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value);
    }
}
