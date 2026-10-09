<?php

namespace App\Policies;

use App\Models\ApprovalLimit;
use App\Models\User;

class ApprovalLimitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('approval_limits.edit');
    }

    public function update(User $user, ApprovalLimit $limit): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('approval_limits.edit');
    }
}
