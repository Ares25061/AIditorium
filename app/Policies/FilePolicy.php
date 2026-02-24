<?php

namespace App\Policies;


use App\FilePermissions;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\CourseUsersRoleEnum;

class FilePolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(FilePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny('You do not have permission to view any files.');
    }
    public function view(User $user, File $file)
    {
        if ($user->hasPermission(FilePermissions::VIEW) || $user->id === $file->user_id) {
            return Response::allow();
        }
        if ($file->is_public){
            return Response::allow();
        }
        if ($file->course_id) {
            return $this->checkCourseAccess($user, $file);
        } // проверка прав в курсе

        return Response::deny("You don't have permission to view this file");

    }
    // проверка для конкретного файла
    private function checkCourseAccess(User $user, File $file): Response
    {
        $course = Course::find($file->course_id);

        if (!$course) {
            return Response::deny('Course not found');
        }

        // проверяем, является ли пользователь учителем в этом курсе
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        // проверяем, является ли пользователь учеником и смотрит ли он свой файл
        if ($user->id === $file->user_id) {
            return Response::allow();
        }

        return Response::deny('You cannot view files from other students in this course');
    }

    // просмотр всех файлов - только для учителя
    public function viewAnyInCourse(User $user, Course $course): Response
    {
        // проверка на просмотр всех файлов в курсе (для учителя)
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if (!empty($userCourse) && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny('Only teachers can view all files in course');
    }

    public function viewStudentFiles(User $user, Course $course): Response
    {
        // учитель может смотреть файлы конкретного ученика
        $userCourse = $user->courses()
            ->where('course_id', $course->id)
            ->first();

        if ($userCourse && $userCourse->pivot->role === CourseUsersRoleEnum::TEACHER->value) {
            return Response::allow();
        }

        return Response::deny('You cannot view this student\'s files');
    }


    public function update(User $user)
    {
        if ($user->hasPermission(FilePermissions::UPDATE)) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to update files");
    }
    public function delete(User $user, File $file)
    {
        if ($user->hasPermission(FilePermissions::DELETE)) {
            return Response::allow();
        }
        if ($user->id === $file->user_id) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to delete files");
    }
}
