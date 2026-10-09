<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\BranchAccess;
use Illuminate\Database\Eloquent\Model;

class BranchPolicy extends BelongsToTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $model instanceof Branch
            && $user->hasPermission('branches.view')
            && app(BranchAccess::class)->allows($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof Branch
            && $user->hasPermission('branches.edit')
            && app(BranchAccess::class)->allows($user, $model)
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin) || $user->hasRole(RoleName::Supervisor));
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof Branch
            && $user->isSuperAdmin()
            && $user->hasPermission('branches.delete')
            && app(BranchAccess::class)->allows($user, $model);
    }

    public function assign(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin))
            && app(BranchAccess::class)->allows($user, $branch);
    }
}
