<?php
// app/Policies/GradePolicy.php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\GradePermissions;
use App\Models\Course;
use App\Models\Grade;
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
                return Response::allow();
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
        // Only teachers can update grades
        if ($grade->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $grade->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                return Response::allow();
            }
        }

        return Response::deny(__('policies.grade.update.deny'));
    }

    public function delete(User $user, Grade $grade)
    {
        // Global permission
        if ($user->hasPermission(GradePermissions::DELETE)) {
            return Response::allow();
        }

        // Teachers can delete grades in their course
        if ($grade->course_id) {
            $userCourse = $user->courses()
                ->where('course_id', $grade->course_id)
                ->first();

            if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
                return Response::allow();
            }
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
}
