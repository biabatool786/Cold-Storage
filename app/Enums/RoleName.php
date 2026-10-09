<?php

namespace App\Enums;

use App\Models\User;

enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Manager = 'Manager';
    case Supervisor = 'Supervisor';
    case Gatekeeper = 'Gatekeeper';
    case Accountant = 'Accountant';

    /**
     * Legacy names that already exist in some databases.
     * Viewer has no documented role, so those users are remapped to Supervisor.
     *
     * @return array<string, self>
     */
    public static function legacyMap(): array
    {
        return [
            'Tenant Admin' => self::Admin,
            'Storekeeper' => self::Supervisor,
            'Quality Officer' => self::Supervisor,
            'Monitoring Operator' => self::Supervisor,
            'Viewer' => self::Supervisor,
        ];
    }

    public function canBeAssignedTo(?int $tenantId, User $actor): bool
    {
        if ($this === self::SuperAdmin) {
            return $actor->isSuperAdmin() && $tenantId === null;
        }

        if ($tenantId === null) {
            return false;
        }

        if ($this === self::Admin) {
            return $actor->isSuperAdmin();
        }

        return $actor->isSuperAdmin() || $actor->hasRole(self::Admin);
    }
}
