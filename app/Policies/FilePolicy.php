<?php

namespace App\Policies;

use App\FilePermissions;
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
        return Response::deny('You do not have permission to view any files.');
    }
    public function view(User $user,File $file)
    {
        if ($user->hasPermission(FilePermissions::VIEW) || $user->id === $file->user_id) {
            return Response::allow();
        }
        if ($file->is_public){
            return Response::allow();
        }
        return Response::deny("You don't have permission to view this file");

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
