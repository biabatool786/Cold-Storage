<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BelongsToTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersView);
    }

    public function view(User $user, Model $model): bool
    {
        if (! $model instanceof User) {
            return false;
        }

        if ($user->is($model)) {
            return true;
        }

        return $user->hasPermission(PermissionName::UsersView)
            && $this->sameTenant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersManage);
    }

    public function update(User $user, Model $model): bool
    {
        if (! $model instanceof User) {
            return false;
        }

        if ($user->is($model)) {
            return $user->hasPermission(PermissionName::ProfileUpdate);
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->hasPermission(PermissionName::UsersManage)
            && $this->sameTenant($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        if (! $model instanceof User || $user->is($model)) {
            return false;
        }

        return $user->hasPermission(PermissionName::UsersManage)
            && $this->sameTenant($user, $model);
    }

    public function assignRole(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return false;
        }

        if ($target->isSuperAdmin() || ($target->hasRole(RoleName::Admin) && ! $user->isSuperAdmin())) {
            return false;
        }

        return $user->hasPermission(PermissionName::UsersManage)
            && $this->sameTenant($user, $target);
    }
}
