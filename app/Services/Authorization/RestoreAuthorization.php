<?php

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RestoreAuthorization
{
    public function allows(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function assert(User $user): void
    {
        if (! $this->allows($user)) {
            throw new AuthorizationException('Only a Super Admin can restore a deleted record.');
        }
    }
}
