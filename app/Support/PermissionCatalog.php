<?php

namespace App\Support;

use App\Enums\RoleName;

class PermissionCatalog
{
    /**
     * Every permission stored for the six production roles.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (RoleName::cases() as $role) {
            foreach (self::for($role) as $name) {
                $names[$name] = $name;
            }
        }

        return array_values($names);
    }

    /**
     * @return list<string>
     */
    public static function for(RoleName $role): array
    {
        $account = ['dashboard.view', 'profile.update'];

        return array_values(array_unique([
            ...$account,
            ...match ($role) {
                RoleName::SuperAdmin => self::superAdmin(),
                RoleName::Admin => self::admin(),
                RoleName::Manager => self::manager(),
                RoleName::Supervisor => self::supervisor(),
                RoleName::Gatekeeper => self::gatekeeper(),
                RoleName::Accountant => self::accountant(),
            },
        ]));
    }

    /**
     * @return list<string>
     */
    private static function superAdmin(): array
    {
        return self::expand([
            'users' => ['view', 'create', 'edit', 'execute', 'print', 'manage'],
            'login_history' => ['view', 'print'],
            'tenants' => ['view', 'manage'],
            'subscriptions' => ['view'],
            'platform' => ['settings', 'reports'],
            'tenant' => ['settings'],
            'audit' => ['view', 'print'],
            'approval_limits' => ['edit'],
            'records' => ['restore'],
            'backup' => ['execute'],
            'branches' => ['view', 'create', 'edit', 'delete'],
            'customers' => ['view', 'create', 'edit', 'delete', 'approve', 'execute', 'print'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'chambers' => ['view', 'create', 'edit', 'delete', 'execute'],
            'bookings' => ['view', 'create', 'edit', 'approve', 'execute', 'print', 'deposit'],
            'gate' => ['view', 'create', 'edit', 'delete', 'approve', 'execute', 'print'],
            'weighbridge' => ['view', 'edit', 'delete', 'approve', 'execute', 'print'],
            'receiving' => ['view'],
            'grn' => ['view', 'create', 'edit', 'delete', 'approve', 'execute', 'print'],
            'batches' => ['view'],
            'inventory' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'transfers' => ['view', 'create', 'approve', 'execute'],
            'dispatch' => ['view', 'create', 'edit', 'delete', 'approve', 'execute', 'print'],
            'monitoring' => ['view', 'create', 'edit', 'approve', 'execute', 'print', 'acknowledge'],
            'billing' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'payments' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'expenses' => ['view', 'create', 'approve', 'execute', 'print'],
            'vendors' => ['view', 'create', 'edit', 'execute', 'print'],
            'accounts' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'maintenance' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'energy' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'labor' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'quality' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'notifications' => ['view', 'create', 'edit', 'execute'],
            'reports' => ['view', 'create', 'execute', 'print'],
            'settings' => ['view', 'edit', 'execute', 'approve'],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function admin(): array
    {
        return self::expand([
            'users' => ['view', 'create', 'edit', 'execute', 'manage'],
            'login_history' => ['view', 'print'],
            'tenant' => ['settings'],
            'audit' => ['view'],
            'branches' => ['view', 'edit'],
            'customers' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'products' => ['view', 'edit'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'chambers' => ['view', 'edit', 'execute'],
            'bookings' => ['view', 'create', 'edit', 'execute', 'print'],
            'gate' => ['view', 'print'],
            'weighbridge' => ['view', 'edit'],
            'receiving' => ['view'],
            'grn' => ['view', 'print'],
            'batches' => ['view'],
            'inventory' => ['view', 'print'],
            'transfers' => ['view'],
            'dispatch' => ['view', 'print'],
            'monitoring' => ['view', 'create', 'edit', 'execute', 'print', 'acknowledge'],
            'billing' => ['view', 'edit', 'print'],
            'payments' => ['view'],
            'expenses' => ['view'],
            'vendors' => ['view', 'create'],
            'maintenance' => ['view', 'create', 'edit', 'execute', 'print'],
            'energy' => ['view', 'create', 'edit'],
            'labor' => ['view', 'create', 'edit', 'execute'],
            'quality' => ['view', 'create', 'edit'],
            'notifications' => ['view', 'create', 'edit', 'execute'],
            'reports' => ['view', 'create', 'execute', 'print'],
            'settings' => ['view', 'edit'],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function manager(): array
    {
        return self::expand([
            'users' => ['view'],
            'login_history' => ['view'],
            'branches' => ['view', 'create'],
            'customers' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'products' => ['view', 'create', 'edit', 'approve'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'chambers' => ['view', 'create', 'approve', 'execute'],
            'bookings' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'gate' => ['view', 'edit', 'approve', 'execute', 'print'],
            'weighbridge' => ['view', 'approve', 'execute', 'print'],
            'receiving' => ['view'],
            'grn' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'batches' => ['view'],
            'inventory' => ['view', 'create', 'approve', 'execute', 'print'],
            'transfers' => ['view', 'create', 'approve', 'execute'],
            'dispatch' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'monitoring' => ['view', 'create', 'approve', 'execute', 'print', 'acknowledge'],
            'billing' => ['view', 'create', 'edit', 'approve', 'print'],
            'payments' => ['view', 'approve', 'execute', 'print'],
            'expenses' => ['view', 'create', 'approve', 'print'],
            'vendors' => ['view'],
            'accounts' => ['view', 'print'],
            'maintenance' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'energy' => ['view', 'create', 'approve', 'print'],
            'labor' => ['view', 'create', 'approve', 'execute'],
            'quality' => ['view', 'create', 'edit', 'approve', 'execute', 'print'],
            'notifications' => ['view', 'execute'],
            'reports' => ['view', 'create', 'execute', 'print'],
            'settings' => ['view'],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function supervisor(): array
    {
        return self::expand([
            'branches' => ['view', 'edit'],
            'customers' => ['view', 'execute'],
            'products' => ['view', 'edit'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'chambers' => ['view', 'edit', 'execute'],
            'bookings' => ['view', 'create', 'edit', 'print'],
            'gate' => ['view'],
            'weighbridge' => ['view', 'execute', 'print'],
            'receiving' => ['view'],
            'grn' => ['view', 'create', 'edit', 'execute', 'print'],
            'batches' => ['view'],
            'inventory' => ['view', 'create', 'execute', 'print'],
            'transfers' => ['view', 'create', 'execute'],
            'dispatch' => ['view', 'create', 'edit', 'execute', 'print'],
            'monitoring' => ['view', 'create', 'edit', 'execute', 'print', 'acknowledge'],
            'billing' => ['print'],
            'payments' => ['view'],
            'expenses' => ['view', 'create'],
            'accounts' => ['view'],
            'maintenance' => ['view', 'create', 'execute'],
            'energy' => ['view', 'create'],
            'labor' => ['view', 'create', 'edit', 'execute'],
            'quality' => ['view', 'create', 'edit', 'execute', 'print'],
            'reports' => ['view', 'print'],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function gatekeeper(): array
    {
        return self::expand([
            'branches' => ['view'],
            'customers' => ['view'],
            'products' => ['view', 'edit'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'gate' => ['view', 'create', 'edit', 'execute', 'print'],
            'weighbridge' => ['view', 'execute', 'print'],
            'receiving' => ['view'],
            'grn' => ['view'],
            'inventory' => ['print'],
            'dispatch' => ['view', 'execute', 'print'],
            'monitoring' => ['view'],
            'maintenance' => ['create'],
            'energy' => ['create'],
            'labor' => ['execute'],
            'quality' => ['view', 'create'],
            'reports' => ['print'],
        ]);
    }

    /**
     * @return list<string>
     */
    private static function accountant(): array
    {
        return self::expand([
            'branches' => ['view'],
            'customers' => ['view', 'edit', 'execute', 'print'],
            'products' => ['view'],
            'varieties' => ['view'],
            'grades' => ['view'],
            'chambers' => ['view'],
            'bookings' => ['view', 'print', 'deposit'],
            'weighbridge' => ['view'],
            'receiving' => ['view'],
            'grn' => ['view', 'execute', 'print'],
            'batches' => ['view'],
            'inventory' => ['view', 'print'],
            'dispatch' => ['view', 'approve', 'print'],
            'billing' => ['view', 'create', 'edit', 'execute', 'print'],
            'payments' => ['view', 'create', 'edit', 'execute', 'print'],
            'expenses' => ['view', 'create', 'execute', 'print'],
            'vendors' => ['view', 'create', 'execute'],
            'accounts' => ['view', 'create', 'execute', 'print'],
            'maintenance' => ['view'],
            'energy' => ['view', 'edit'],
            'labor' => ['view', 'create', 'edit', 'execute', 'print'],
            'quality' => ['view', 'edit'],
            'notifications' => ['view', 'execute'],
            'reports' => ['view', 'create', 'execute', 'print'],
            'settings' => ['view'],
        ]);
    }

    /**
     * @param  array<string, list<string>>  $grants
     * @return list<string>
     */
    private static function expand(array $grants): array
    {
        $names = [];

        foreach ($grants as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $module.'.'.$action;
            }
        }

        return $names;
    }
}
