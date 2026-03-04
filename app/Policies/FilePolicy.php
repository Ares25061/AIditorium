<?php

namespace App\Policies;


use App\Enums\CourseUsersRoleEnum;
use App\Enums\FilePermissions;
use App\Models\Course;
use App\Models\File;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy
{
    public function viewAny(User $user)
    {
        if ($user->hasPermission(FilePermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.file.view_any.deny'));
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

        return Response::deny(__('policies.file.view.deny'));

    }
    // проверка для конкретного файла
    private function checkCourseAccess(User $user, File $file): Response
    {
        $course = Course::find($file->course_id);

        if (!$course) {
            return Response::deny(__('messages.not_found', ['item' => __('messages.items.course')]));
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

        return Response::deny(__('policies.file.check_course_access.deny'));
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

        return Response::deny(__('policies.file.view_any_in_course.deny'));
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

        return Response::deny(__('policies.file.view_student_files.deny'));
    }


    public function update(User $user)
    {
        if ($user->hasPermission(FilePermissions::UPDATE)) {
            return Response::allow();
        }
        return Response::deny(__('policies.file.update.deny'));
    }
    public function delete(User $user, File $file)
    {
        if ($user->hasPermission(FilePermissions::DELETE)) {
            return Response::allow();
        }
        if ($user->id === $file->user_id) {
            return Response::allow();
        }
        return Response::deny(__('policies.file.delete.deny'));
    }
}
