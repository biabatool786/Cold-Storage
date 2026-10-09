<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\ApprovalLimit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ApprovalLimitEvaluator
{
    public function managerLimit(string $code): ?string
    {
        return ApprovalLimit::platform($code)?->manager_limit;
    }

    public function withinManagerLimit(string $code, float|int|string $value): bool
    {
        $limit = $this->managerLimit($code);

        if ($limit === null) {
            return false;
        }

        return (float) $value <= (float) $limit;
    }

    public function assertCanApprove(User $actor, string $code, float|int|string $value): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->hasRole(RoleName::Manager) && $this->withinManagerLimit($code, $value)) {
            return;
        }

        throw new AuthorizationException('This amount is above the configured approval limit.');
    }
}
