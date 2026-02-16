<?php

namespace App\Policies;

use App\Models\User;
use App\RolePermissions;
use App\UserPermissions;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function view(User $user, User $model)
    {
        if ($user->hasPermission(UserPermissions::VIEW) || $user->id === $model->id) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to view users");

    }
    public function viewList(User $user)
    {
        if ($user->hasPermission(UserPermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny("You don't have permission to view list users");
    }
    public function update(User $user, User $model)
    {
        if($user->id === $model->id){
            return Response::allow();
        }
        if (!$user->hasPermission(UserPermissions::UPDATE)) {
            return Response::deny("You don't have permission to update users");
        }
        return Response::allow();
    }
    public function delete(User $user, User $model)
    {
        if (!$user->hasPermission(UserPermissions::DELETE)) {
            return Response::deny("You don't have permission to delete users");
        }
        if ($user->id === $model->id) {
            return Response::deny("You cant delete yourself");
        }
        return Response::allow();
    }
    public function setRole(User $user, User $model)
    {
        if (!$user->hasPermission(RolePermissions::SET))
        {
            return Response::deny("You don't have permission to set role users");
        }
        if ($user->id === $model->id)
        {
            return Response::deny("You cant change role yourself");
        }
        return Response::allow();
    }
}
