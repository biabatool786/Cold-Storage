<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;

class ShiftEditWindow
{
    public function needsManagerApproval(User $actor, int $ownerId, CarbonInterface $createdAt): bool
    {
        if (! $actor->hasAnyRole([RoleName::Supervisor, RoleName::Gatekeeper])) {
            return false;
        }

        if ((int) $actor->id !== $ownerId) {
            return true;
        }

        return ! $createdAt->isSameDay(now());
    }

    public function assertCanEdit(User $actor, int $ownerId, CarbonInterface $createdAt, ?User $approver = null): void
    {
        if (! $actor->hasAnyRole([RoleName::Supervisor, RoleName::Gatekeeper])) {
            return;
        }

        if ((int) $actor->id !== $ownerId) {
            throw new AuthorizationException('You can only edit your own operational entries.');
        }

        if ($createdAt->isSameDay(now())) {
            return;
        }

        if ($this->hasManagerApproval($actor, $approver)) {
            return;
        }

        throw new AuthorizationException('This entry is outside the same-day window. Manager approval is required.');
    }

    private function hasManagerApproval(User $actor, ?User $approver): bool
    {
        if (! $approver instanceof User || $approver->is($actor)) {
            return false;
        }

        return $approver->isSuperAdmin() || $approver->hasRole(RoleName::Manager);
    }
}
