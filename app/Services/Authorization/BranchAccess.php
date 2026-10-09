<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;

class BranchAccess
{
    public function allows(User $user, Branch $branch): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->tenant_id === null || (int) $user->tenant_id !== (int) $branch->tenant_id) {
            return false;
        }

        if ($user->hasRole(RoleName::Admin)) {
            return true;
        }

        return $user->branches()->whereKey($branch->id)->exists();
    }
}
