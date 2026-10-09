<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission(PermissionName::TenantsView);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission(PermissionName::TenantsView);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission(PermissionName::TenantsManage);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return $user->hasPermission(PermissionName::TenantsManage);
        }

        return $user->hasPermission(PermissionName::TenantSettings)
            && $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $tenant->id;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission(PermissionName::TenantsManage);
    }
}
