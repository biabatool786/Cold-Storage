<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Models\User;

class Navigation
{
    /**
     * @return list<array{label: string, items: list<array{label: string, href: string, active: bool}>}>
     */
    public static function groups(User $user): array
    {
        $groups = [];

        $overview = [];
        if ($user->hasPermission(PermissionName::DashboardView)) {
            $overview[] = self::link('Dashboard', route('dashboard'), request()->routeIs('dashboard'));
        }
        if ($overview !== []) {
            $groups[] = ['label' => 'Overview', 'items' => $overview];
        }

        $platform = [];
        if ($user->hasPermission(PermissionName::TenantsView)) {
            $platform[] = self::link('Tenants', route('tenants.index'), request()->routeIs('tenants.*'));
        }
        if ($user->hasPermission(PermissionName::PlatformReports)) {
            $platform[] = self::link('Reports', route('reports.index'), request()->routeIs('reports.index'));
        }
        if ($user->hasPermission('approval_limits.edit')) {
            $platform[] = self::link('Approval limits', route('approval-limits.index'), request()->routeIs('approval-limits.*'));
        }
        if ($platform !== []) {
            $groups[] = ['label' => 'Platform', 'items' => $platform];
        }

        $workspace = [];
        foreach ([
            ['Customers', 'customers.index', 'customers.*', 'customers.view'],
            ['Products', 'commodities.index', 'commodities.*', 'products.view'],
            ['Chambers', 'chambers.index', 'chambers.*', 'chambers.view'],
            ['Storage layout', 'chambers.index', 'chambers.show', 'chambers.view'],
            ['Occupancy map', 'occupancy.index', 'occupancy.*', 'chambers.view'],
            ['Bookings', 'bookings.index', 'bookings.index', 'bookings.view'],
            ['Availability', 'bookings.availability', 'bookings.availability', 'bookings.view'],
            ['Waiting list', 'bookings.waiting', 'bookings.waiting', 'bookings.view'],
            ['Seasonal contracts', 'bookings.contracts', 'bookings.contracts', 'bookings.view'],
            ['Gate today', 'gates.index', 'gates.index', 'gate.view'],
            ['Expected vehicles', 'gates.expected', 'gates.expected', 'gate.view'],
            ['Visitors', 'gates.visitors', 'gates.visitors', 'gate.view'],
            ['Blocked vehicles', 'gates.blocked', 'gates.blocked', 'gate.view'],
            ['Gate history', 'gates.history', 'gates.history', 'gate.view'],
            ['Weighbridge', 'weighments.index', 'weighments.index', 'weighbridge.view'],
            ['Tolerance alerts', 'weighments.alerts', 'weighments.alerts', 'weighbridge.view'],
            ['Calibration log', 'weighments.calibration', 'weighments.calibration', 'weighbridge.view'],
            ['Expected arrivals', 'receiving.expected', 'receiving.expected', 'receiving.view'],
            ['GRN', 'grns.index', 'grns.*', 'grn.view'],
            ['Put-away', 'putaways.index', 'putaways.*', 'inventory.view'],
            ['Stock', 'inventory.index', 'inventory.index', 'inventory.view'],
            ['Stock ledger', 'inventory.ledger', 'inventory.ledger', 'inventory.print'],
            ['FIFO/FEFO', 'inventory.suggestions', 'inventory.suggestions', 'inventory.view'],
            ['Transfers', 'inventory.transfers', 'inventory.transfers', 'transfers.view'],
            ['Holds', 'inventory.holds', 'inventory.holds', 'inventory.view'],
            ['VAS', 'inventory.services', 'inventory.services', 'inventory.view'],
            ['Shrinkage', 'inventory.shrinkage', 'inventory.shrinkage', 'inventory.view'],
            ['Counts', 'inventory.counts', 'inventory.counts', 'inventory.view'],
            ['Variances', 'inventory.variances', 'inventory.variances', 'inventory.view'],
            ['Aging', 'inventory.aging', 'inventory.aging', 'inventory.view'],
            ['Stock export', 'inventory.export', 'inventory.export', 'inventory.print'],
            ['Dispatch', 'deliveries.index', 'deliveries.*', 'dispatch.view'],
            ['Monitoring', 'monitoring.index', 'monitoring.*', 'monitoring.view'],
            ['Invoices', 'invoices.index', 'invoices.*', 'billing.view'],
            ['Payments', 'payments.index', 'payments.*', 'payments.view'],
            ['Expenses', 'expenses.index', 'expenses.*', 'expenses.view'],
            ['Equipment', 'assets.index', 'assets.*', 'maintenance.view'],
            ['Energy', 'energy.index', 'energy.*', 'energy.view'],
            ['Labor', 'labor.index', 'labor.*', 'labor.view'],
            ['Quality', 'quality-checks.index', 'quality-checks.*', 'quality.view'],
            ['Notices', 'notices.index', 'notices.*', 'notifications.view'],
        ] as [$label, $route, $active, $permission]) {
            if ($user->hasPermission($permission)) {
                $workspace[] = self::link($label, route($route), request()->routeIs($active));
            }
        }

        if ($user->hasPermission('branches.view')) {
            $workspace[] = self::link('Branches', route('branches.index'), request()->routeIs('branches.*'));
        }

        if ($user->hasPermission(PermissionName::UsersView)) {
            $workspace[] = self::link('Users & Roles', route('users.index'), request()->routeIs('users.*'));
        }
        if ($user->hasPermission('users.edit')) {
            $workspace[] = self::link('Permission matrix', route('security.matrix'), request()->routeIs('security.matrix'));
        }
        if ($user->hasPermission('login_history.view')) {
            $workspace[] = self::link('Login history', route('login-history.index'), request()->routeIs('login-history.*'));
        }

        if (! $user->hasPermission(PermissionName::PlatformReports) && $user->hasPermission(PermissionName::ReportsView)) {
            $workspace[] = self::link('Reports', route('reports.index'), request()->routeIs('reports.index'));
        }

        if ($workspace !== []) {
            $groups[] = ['label' => 'Workspace', 'items' => $workspace];
        }

        $admin = [];
        if ($user->hasPermission(PermissionName::PlatformSettings) || $user->hasPermission(PermissionName::TenantSettings)) {
            $admin[] = self::link('Settings', route('settings.edit'), request()->routeIs('settings.*'));
        }
        if ($user->hasPermission(PermissionName::AuditView)) {
            $admin[] = self::link('Audit Logs', route('audit.index'), request()->routeIs('audit.index'));
        }
        $admin[] = self::link('Profile', route('profile.edit'), request()->routeIs('profile.*'));
        $groups[] = ['label' => 'Account', 'items' => $admin];

        return $groups;
    }

    /**
     * @return array{label: string, href: string, active: bool}
     */
    private static function link(string $label, string $href, bool $active): array
    {
        return compact('label', 'href', 'active');
    }
}
