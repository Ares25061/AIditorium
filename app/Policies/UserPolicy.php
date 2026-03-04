<?php

namespace App\Policies;

use App\Enums\RolePermissions;
use App\Enums\UserPermissions;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function view(User $user, User $model)
    {
        if ($user->hasPermission(UserPermissions::VIEW) || $user->id === $model->id) {
            return Response::allow();
        }
        return Response::deny(__('policies.user.view.deny'));

    }
    public function viewList(User $user)
    {
        if ($user->hasPermission(UserPermissions::VIEW_LIST)) {
            return Response::allow();
        }
        return Response::deny(__('policies.user.view_list.deny'));
    }
    public function update(User $user, User $model)
    {
        if($user->id === $model->id){
            return Response::allow();
        }
        if (!$user->hasPermission(UserPermissions::UPDATE)) {
            return Response::deny(__('policies.user.update.deny'));
        }
        return Response::allow();
    }
    public function delete(User $user, User $model)
    {
        if (!$user->hasPermission(UserPermissions::DELETE)) {
            return Response::deny(__('policies.user.delete.deny'));
        }
        if ($user->id === $model->id) {
            return Response::deny(__('policies.user.delete.cannot_delete_self'));
        }
        return Response::allow();
    }
    public function setRole(User $user, User $model)
    {
        if (!$user->hasPermission(RolePermissions::SET))
        {
            return Response::deny(__('policies.user.set_role.deny'));
        }
        if ($user->id === $model->id)
        {
            return Response::deny(__('policies.user.set_role.cannot_change_self'));
        }
        return Response::allow();
    }
}
