<?php

namespace App\Policies;

use App\Enums\CourseUsersRoleEnum;
use App\Enums\DisciplinePermissions;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DisciplinePolicy
{

    public function viewAny(User $user)
    {
        if ($user->hasPermission(DisciplinePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny('You do not have permission to view any disciplines.');
    }
    public function view(User $user,Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::VIEW)) {
            return Response::allow();
        }
        if (!empty($user->courses()->where('course_id', $course->id)->first())){
            return Response::allow();
        }
        return Response::deny("You don't have permission to view this discipline in course");

    }
    public function create(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::CREATE)) {
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
        return Response::deny("You don't have permission to create disciplines in this course");
    }
    public function update(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::UPDATE)) {
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
        return Response::deny("You don't have permission to update disciplines in this course");
    }
    public function delete(User $user, Course $course)
    {
        if ($user->hasPermission(DisciplinePermissions::DELETE)) {
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
        return Response::deny("You don't have permission to destroy this discipline in this course");
    }
    public function viewDisciplines(User $user, Course $course)
    {
        if ($user->courses()->where('course_id', $course->id)->first())
        {
            return Response::allow();
        }
        return Response::deny("You don't enrolled in this course");
    }
}
