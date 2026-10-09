<?php

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class MakerChecker
{
    public function denies(User $actor, int|string|null $createdBy): bool
    {
        return $createdBy !== null && (int) $createdBy === (int) $actor->id;
    }

    public function assertCanApprove(User $actor, int|string|null $createdBy): void
    {
        if ($this->denies($actor, $createdBy)) {
            throw new AuthorizationException('The user who created this record cannot approve it.');
        }
    }
}
