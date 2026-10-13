<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /**
     * Hierarchical sidebar modules for the authenticated layout.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     type: 'link'|'group',
     *     href?: string,
     *     active: bool,
     *     open: bool,
     *     items: list<array{label: string, href: string, active: bool}>
     * }>
     */
    public static function modules(User $user): array
    {
        $modules = [];

        if ($user->hasPermission(PermissionName::DashboardView)) {
            $modules[] = self::leaf('overview', 'Dashboard', 'home', route('dashboard'), request()->routeIs('dashboard'));
        }

        $platform = self::collect($user, [
            ['Tenants', 'tenants.index', 'tenants.*', PermissionName::TenantsView->value],
            ['Platform reports', 'reports.index', 'reports.index', PermissionName::PlatformReports->value],
            ['Approval limits', 'approval-limits.index', 'approval-limits.*', 'approval_limits.edit'],
        ]);
        if ($platform !== []) {
            $modules[] = self::group('platform', 'Platform', 'building', $platform);
        }

        $facility = self::collect($user, [
            ['Chambers', 'chambers.index', ['chambers.index', 'chambers.show'], 'chambers.view'],
            ['Occupancy map', 'occupancy.index', 'occupancy.*', 'chambers.view'],
            ['Branches', 'branches.index', 'branches.*', 'branches.view'],
        ]);
        if ($facility !== []) {
            $modules[] = self::group('facility', 'Facility & layout', 'warehouse', $facility);
        }

        $customers = self::collect($user, [
            ['Customer register', 'customers.index', 'customers.*', 'customers.view'],
        ]);
        if ($customers !== []) {
            $modules[] = self::group('customers', 'Customers', 'users', $customers);
        }

        $products = self::collect($user, [
            ['Products', 'commodities.index', 'commodities.*', 'products.view'],
        ]);
        if ($products !== []) {
            $modules[] = self::group('products', 'Product master', 'package', $products);
        }

        $bookings = self::collect($user, [
            ['Bookings', 'bookings.index', ['bookings.index', 'bookings.show', 'bookings.create'], 'bookings.view'],
            ['Availability', 'bookings.availability', 'bookings.availability', 'bookings.view'],
            ['Waiting list', 'bookings.waiting', 'bookings.waiting', 'bookings.view'],
            ['Seasonal contracts', 'bookings.contracts', 'bookings.contracts', 'bookings.view'],
        ]);
        if ($bookings !== []) {
            $modules[] = self::group('bookings', 'Bookings', 'calendar', $bookings);
        }

        $gate = self::collect($user, [
            ['Gate today', 'gates.index', ['gates.index', 'gates.show'], 'gate.view'],
            ['Expected vehicles', 'gates.expected', 'gates.expected', 'gate.view'],
            ['Visitors', 'gates.visitors', 'gates.visitors', 'gate.view'],
            ['Blocked vehicles', 'gates.blocked', 'gates.blocked', 'gate.view'],
            ['Gate history', 'gates.history', 'gates.history*', 'gate.view'],
        ]);
        if ($gate !== []) {
            $modules[] = self::group('gate', 'Gate', 'truck', $gate);
        }

        $weighbridge = self::collect($user, [
            ['Weighments', 'weighments.index', ['weighments.index', 'weighments.show'], 'weighbridge.view'],
            ['Tolerance alerts', 'weighments.alerts', 'weighments.alerts', 'weighbridge.view'],
            ['Calibration log', 'weighments.calibration', 'weighments.calibration', 'weighbridge.view'],
        ]);
        if ($weighbridge !== []) {
            $modules[] = self::group('weighbridge', 'Weighbridge', 'scale', $weighbridge);
        }

        $receiving = self::collect($user, [
            ['Expected arrivals', 'receiving.expected', 'receiving.expected', 'receiving.view'],
            ['GRN', 'grns.index', 'grns.*', 'grn.view'],
            ['Put-away', 'putaways.index', 'putaways.*', 'inventory.view'],
        ]);
        if ($receiving !== []) {
            $modules[] = self::group('receiving', 'Receiving', 'inbox', $receiving);
        }

        $inventory = self::collect($user, [
            ['Stock', 'inventory.index', ['inventory.index', 'inventory.lots.*', 'lots.*'], 'inventory.view'],
            ['Stock ledger', 'inventory.ledger', 'inventory.ledger', 'inventory.print'],
            ['FIFO / FEFO', 'inventory.suggestions', 'inventory.suggestions', 'inventory.view'],
            ['Transfers', 'inventory.transfers', 'inventory.transfers*', 'transfers.view'],
            ['Holds', 'inventory.holds', 'inventory.holds*', 'inventory.view'],
            ['VAS', 'inventory.services', 'inventory.services*', 'inventory.view'],
            ['Shrinkage', 'inventory.shrinkage', 'inventory.shrinkage', 'inventory.view'],
            ['Counts', 'inventory.counts', 'inventory.counts*', 'inventory.view'],
            ['Variances', 'inventory.variances', 'inventory.variances*', 'inventory.view'],
            ['Aging', 'inventory.aging', 'inventory.aging', 'inventory.view'],
            ['Stock export', 'inventory.export', 'inventory.export', 'inventory.print'],
        ]);
        if ($inventory !== []) {
            $modules[] = self::group('inventory', 'Inventory', 'layers', $inventory);
        }

        $dispatch = self::collect($user, [
            ['Delivery orders', 'deliveries.index', 'deliveries.*', 'dispatch.view'],
            ['Returns', 'dispatch.returns', 'dispatch.returns*', 'dispatch.view'],
            ['Transfer-out', 'dispatch.transfer-outs', 'dispatch.transfer-outs*', 'dispatch.view'],
        ]);
        if ($dispatch !== []) {
            $modules[] = self::group('dispatch', 'Dispatch', 'send', $dispatch);
        }

        $monitoring = self::collect($user, [
            ['Live dashboard', 'monitoring.index', 'monitoring.index', 'monitoring.view'],
            ['Readings history', 'monitoring.history', 'monitoring.history', 'monitoring.view'],
            ['Sensors', 'monitoring.sensors', 'monitoring.sensors*', 'monitoring.view'],
            ['Alarms', 'monitoring.alarms', 'monitoring.alarms*', 'monitoring.view'],
            ['Manual temp log', 'monitoring.manual-log', 'monitoring.manual-log', 'monitoring.create'],
            ['Defrost logs', 'monitoring.defrost', 'monitoring.defrost*', 'monitoring.create'],
            ['Calibrations', 'monitoring.calibrations', 'monitoring.calibrations*', 'monitoring.view'],
            ['Excursions', 'monitoring.excursions', 'monitoring.excursions*', 'monitoring.print'],
            ['Incidents', 'monitoring.incidents', 'monitoring.incidents', 'monitoring.view'],
        ]);
        if ($monitoring !== []) {
            $modules[] = self::group('monitoring', 'Monitoring', 'thermometer', $monitoring);
        }

        $finance = self::collect($user, [
            ['Invoices', 'invoices.index', 'invoices.*', 'billing.view'],
            ['Tariffs & rates', 'billing.tariffs', 'billing.tariffs*', 'billing.view'],
            ['Accrued charges', 'billing.accrued', 'billing.accrued', 'billing.view'],
            ['Bulk invoice run', 'billing.bulk', 'billing.bulk*', 'billing.view'],
            ['Billing settings', 'billing.settings', 'billing.settings*', 'billing.view'],
            ['Payments', 'payments.index', 'payments.index', 'payments.view'],
            ['Record payment', 'payments.create', 'payments.create', 'payments.create'],
            ['Deposits', 'payments.deposits', 'payments.deposits', 'payments.view'],
            ['Customer ledger', 'payments.ledger', 'payments.ledger*', 'payments.view'],
            ['Receivable aging', 'payments.aging', 'payments.aging*', 'payments.view'],
            ['Bad debt', 'payments.write-offs', 'payments.write-offs*', 'payments.view'],
            ['Stock loans', 'payments.loans', 'payments.loans*', 'payments.view'],
            ['Expenses', 'expenses.index', 'expenses.index', 'expenses.view'],
            ['Vendors', 'vendors.index', 'vendors.*', 'vendors.view'],
            ['Petty cash', 'accounts.petty-cash', 'accounts.petty-cash*', 'accounts.view'],
            ['Bank reconciliation', 'accounts.bank', 'accounts.bank*', 'accounts.execute'],
            ['Day / cash book', 'accounts.day-book', 'accounts.day-book*', 'accounts.view'],
            ['Profit & loss', 'accounts.profit-loss', 'accounts.profit-loss*', 'accounts.view'],
            ['Accounting export', 'accounts.export', 'accounts.export*', 'accounts.execute'],
            ['Period close', 'accounts.periods', 'accounts.periods*', 'accounts.view'],
        ]);
        // Exact accounts matrix: day book ≠ manager; P&L/petty ≠ supervisor; bank/export = execute roles.
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin()) {
            $finance = array_values(array_filter(
                $finance,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Petty cash', 'Bank reconciliation', 'Profit & loss', 'Accounting export', 'Period close', 'Vendors',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Manager) && ! $user->isSuperAdmin()) {
            $finance = array_values(array_filter(
                $finance,
                fn (array $item): bool => ! in_array($item['label'], ['Day / cash book', 'Bank reconciliation', 'Accounting export', 'Period close'], true)
            ));
        }
        if ($finance !== []) {
            $modules[] = self::group('finance', 'Finance', 'currency', $finance);
        }

        $equipment = self::collect($user, [
            ['Assets', 'assets.index', 'assets.*', 'maintenance.view'],
            ['Preventive maintenance', 'maintenance.schedules', 'maintenance.schedules*', 'maintenance.view'],
            ['Breakdown tickets', 'maintenance.tickets', 'maintenance.tickets*', 'maintenance.create'],
            ['Work orders', 'maintenance.work-orders', 'maintenance.work-orders*', 'maintenance.view'],
            ['Spare parts', 'maintenance.spares', 'maintenance.spares*', 'maintenance.view'],
            ['AMC / Warranty', 'maintenance.contracts', 'maintenance.contracts*', 'maintenance.view'],
            ['Downtime & cost history', 'maintenance.history', 'maintenance.history*', 'maintenance.view'],
        ]);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $equipment = array_values(array_filter(
                $equipment,
                fn (array $item): bool => $item['label'] === 'Breakdown tickets'
            ));
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $equipment = array_values(array_filter(
                $equipment,
                fn (array $item): bool => in_array($item['label'], [
                    'Assets', 'Spare parts', 'AMC / Warranty', 'Downtime & cost history',
                ], true)
            ));
        }
        if ($equipment !== []) {
            $modules[] = self::group('equipment', 'Equipment & Maintenance', 'wrench', $equipment);
        }

        $energy = self::collect($user, [
            ['Electricity meter readings', 'energy.readings', 'energy.readings*', 'energy.view'],
            ['Generator logs', 'energy.generators', 'energy.generators*', 'energy.view'],
            ['Power-cut & backup logs', 'energy.power-events', 'energy.power-events*', 'energy.create'],
            ['Meter-to-chamber mapping', 'energy.mappings', 'energy.mappings*', 'energy.edit'],
            ['Consumption & cost reports', 'energy.reports', 'energy.reports*', 'energy.view'],
            ['Peak load alerts', 'energy.peak-alerts', 'energy.peak-alerts*', 'energy.edit'],
            ['Energy billing allocation', 'energy.allocations', 'energy.allocations*', 'energy.edit'],
        ]);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor])) {
            $energy = array_values(array_filter(
                $energy,
                fn (array $item): bool => $item['label'] === 'Power-cut & backup logs'
            ));
        }
        if ($user->hasRole(RoleName::Admin) && ! $user->isSuperAdmin()) {
            $energy = array_values(array_filter(
                $energy,
                fn (array $item): bool => $item['label'] !== 'Energy billing allocation'
            ));
        }
        if ($user->hasRole(RoleName::Manager) && ! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Admin)) {
            $energy = array_values(array_filter(
                $energy,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Meter-to-chamber mapping', 'Peak load alerts', 'Energy billing allocation',
                ], true)
            ));
            if (Route::has('energy.peak-alerts')) {
                $energy[] = self::link('Peak load alerts', route('energy.peak-alerts'), request()->routeIs('energy.peak-alerts*'));
            }
            if (Route::has('energy.allocations')) {
                $energy[] = self::link('Energy billing allocation', route('energy.allocations'), request()->routeIs('energy.allocations*'));
            }
        }
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $energy = array_values(array_filter(
                $energy,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Meter-to-chamber mapping', 'Peak load alerts', 'Energy billing allocation',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $energy = array_values(array_filter(
                $energy,
                fn (array $item): bool => in_array($item['label'], [
                    'Consumption & cost reports', 'Energy billing allocation',
                ], true)
            ));
        }
        if ($energy !== []) {
            $modules[] = self::group('energy', 'Energy & Power', 'thermometer', $energy);
        }

        $labor = self::collect($user, [
            ['Employee / labor master', 'labor.employees', 'labor.employees*', 'labor.view'],
            ['Attendance', 'labor.attendance', 'labor.attendance*', 'labor.execute'],
            ['Shift scheduling', 'labor.shifts', 'labor.shifts*', 'labor.create'],
            ['Loading / unloading tasks', 'labor.tasks', 'labor.tasks*', 'labor.execute'],
            ['Work done & wage sheets', 'labor.wage-sheets', 'labor.wage-sheets*', 'labor.create'],
            ['Overtime', 'labor.overtime', 'labor.overtime*', 'labor.view'],
            ['Labor advances', 'labor.advances', 'labor.advances*', 'labor.create'],
            ['Payroll', 'labor.payroll', 'labor.payroll*', 'labor.execute'],
            ['PPE & safety', 'labor.ppe', 'labor.ppe*', 'labor.create'],
            ['Leave management', 'labor.leave', 'labor.leave*', 'labor.create'],
        ]);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor])) {
            $labor = array_values(array_filter(
                $labor,
                fn (array $item): bool => in_array($item['label'], [
                    'Attendance', 'Shift scheduling', 'Overtime', 'Leave management',
                ], true)
            ));
            if (Route::has('labor.overtime') && ! collect($labor)->contains(fn ($i) => $i['label'] === 'Overtime')) {
                $labor[] = self::link('Overtime', route('labor.overtime'), request()->routeIs('labor.overtime*'));
            }
            if (Route::has('labor.leave') && ! collect($labor)->contains(fn ($i) => $i['label'] === 'Leave management')) {
                $labor[] = self::link('Leave management', route('labor.leave'), request()->routeIs('labor.leave*'));
            }
            if (Route::has('labor.shifts') && ! collect($labor)->contains(fn ($i) => $i['label'] === 'Shift scheduling')) {
                $labor[] = self::link('Shift scheduling', route('labor.shifts'), request()->routeIs('labor.shifts*'));
            }
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $labor = array_values(array_filter(
                $labor,
                fn (array $item): bool => in_array($item['label'], [
                    'Employee / labor master', 'Overtime', 'Labor advances', 'Payroll',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Admin) && ! $user->isSuperAdmin()) {
            $labor = array_values(array_filter(
                $labor,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Loading / unloading tasks', 'Work done & wage sheets', 'Labor advances', 'Payroll', 'Leave management',
                ], true)
            ));
            if (Route::has('labor.overtime') && ! collect($labor)->contains(fn ($i) => $i['label'] === 'Overtime')) {
                $labor[] = self::link('Overtime', route('labor.overtime'), request()->routeIs('labor.overtime*'));
            }
        }
        if ($user->hasRole(RoleName::Manager) && ! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Admin)) {
            $labor = array_values(array_filter(
                $labor,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Shift scheduling', 'Overtime',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $labor = array_values(array_filter(
                $labor,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Payroll', 'Work done & wage sheets',
                ], true)
            ));
        }
        if ($labor !== []) {
            $modules[] = self::group('labor', 'Labor & Attendance', 'users', $labor);
        }

        $quality = self::collect($user, [
            ['Inspection templates', 'quality.templates', 'quality.templates*', 'quality.edit'],
            ['Inspections', 'quality.inspections', 'quality.inspections*', 'quality.execute'],
            ['NCR & corrective actions', 'quality.ncrs', 'quality.ncrs*', 'quality.create'],
            ['Pest control & sanitation', 'quality.pest', 'quality.pest*', 'quality.create'],
            ['Lot traceability', 'quality.traceability', 'quality.traceability*', 'quality.view'],
            ['Certificates & licenses', 'quality.certificates', 'quality.certificates*', 'quality.view'],
            ['Incidents & accidents', 'quality.incidents', 'quality.incidents*', 'quality.create'],
            ['Insurance policies', 'quality.policies', 'quality.policies*', 'quality.view'],
            ['Insurance claims', 'quality.claims', 'quality.claims*', 'quality.create'],
            ['Gas & fire safety logs', 'quality.safety', 'quality.safety*', 'quality.create'],
            ['SOP library', 'quality.sops', 'quality.sops*', 'quality.view'],
        ]);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor])) {
            $quality = array_values(array_filter(
                $quality,
                fn (array $item): bool => in_array($item['label'], [
                    'NCR & corrective actions', 'Incidents & accidents', 'Gas & fire safety logs', 'SOP library',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $quality = array_values(array_filter(
                $quality,
                fn (array $item): bool => in_array($item['label'], [
                    'Lot traceability', 'Certificates & licenses', 'Insurance policies', 'Insurance claims', 'SOP library',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Admin) && ! $user->isSuperAdmin()) {
            $quality = array_values(array_filter(
                $quality,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Inspections', 'Insurance claims',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $quality = array_values(array_filter(
                $quality,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Inspection templates', 'Insurance claims',
                ], true)
            ));
        }
        if ($quality !== []) {
            $modules[] = self::group('quality', 'Quality, Safety & Compliance', 'shield', $quality);
        }

        $notifications = self::collect($user, [
            ['Notification Dashboard', 'notifications.dashboard', 'notifications.dashboard', 'notifications.view'],
            ['Message Templates', 'notifications.templates', 'notifications.templates*', 'notifications.view'],
            ['Gateway Settings', 'notifications.gateways', 'notifications.gateways*', 'notifications.edit'],
            ['Role Alert Preferences', 'notifications.preferences', 'notifications.preferences*', 'notifications.edit'],
            ['Customer Message', 'notifications.compose', 'notifications.compose*', 'notifications.execute'],
            ['Staff Announcements', 'notifications.announcements', 'notifications.announcements*', 'notifications.execute'],
            ['Message Log', 'notifications.logs', 'notifications.logs*', 'notifications.view'],
        ]);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Accountant])) {
            $notifications = array_values(array_filter(
                $notifications,
                fn (array $item): bool => $item['label'] === 'Notification Dashboard'
            ));
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $notifications = array_values(array_filter(
                $notifications,
                fn (array $item): bool => in_array($item['label'], [
                    'Notification Dashboard', 'Customer Message', 'Message Log',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $notifications = array_values(array_filter(
                $notifications,
                fn (array $item): bool => in_array($item['label'], [
                    'Notification Dashboard', 'Message Templates',
                ], true)
            ));
        }
        if ($user->hasRole(RoleName::Manager) && ! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Admin)) {
            $notifications = array_values(array_filter(
                $notifications,
                fn (array $item): bool => ! in_array($item['label'], [
                    'Gateway Settings', 'Role Alert Preferences',
                ], true)
            ));
        }
        if ($notifications !== []) {
            $modules[] = self::group('notifications', 'Notifications & Communication', 'bell', $notifications);
        }

        $security = self::collect($user, [
            ['Users & roles', 'users.index', 'users.*', PermissionName::UsersView->value],
            ['Permission matrix', 'security.matrix', 'security.matrix', 'users.edit'],
            ['Login history', 'login-history.index', 'login-history.*', 'login_history.view'],
        ]);
        if ($security !== []) {
            $modules[] = self::group('security', 'Users & security', 'shield', $security);
        }

        if (! $user->hasPermission(PermissionName::PlatformReports) && $user->hasPermission(PermissionName::ReportsView)) {
            $modules[] = self::leaf('reports', 'Reports', 'chart', route('reports.index'), request()->routeIs('reports.index'));
        }

        $account = [];
        if ($user->hasPermission(PermissionName::PlatformSettings) || $user->hasPermission(PermissionName::TenantSettings)) {
            $account[] = self::link('Settings', route('settings.edit'), request()->routeIs('settings.*'));
        }
        if ($user->hasPermission(PermissionName::AuditView)) {
            $account[] = self::link('Audit logs', route('audit.index'), request()->routeIs('audit.index'));
        }
        $account[] = self::link('Profile', route('profile.edit'), request()->routeIs('profile.*'));
        $modules[] = self::group('account', 'Account', 'user', $account);

        return $modules;
    }

    /**
     * Backward-compatible flat groups (tests / legacy callers).
     *
     * @return list<array{label: string, items: list<array{label: string, href: string, active: bool}>}>
     */
    public static function groups(User $user): array
    {
        $groups = [];
        foreach (self::modules($user) as $module) {
            if ($module['type'] === 'link') {
                $groups[] = [
                    'label' => $module['label'],
                    'items' => [self::link($module['label'], $module['href'], $module['active'])],
                ];
                continue;
            }
            if ($module['items'] !== []) {
                $groups[] = ['label' => $module['label'], 'items' => $module['items']];
            }
        }

        return $groups;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string|list<string>, 3: string}>  $rows
     * @return list<array{label: string, href: string, active: bool}>
     */
    private static function collect(User $user, array $rows): array
    {
        $items = [];
        foreach ($rows as [$label, $routeName, $activePattern, $permission]) {
            if (! $user->hasPermission($permission) || ! Route::has($routeName)) {
                continue;
            }
            $items[] = self::link($label, route($routeName), self::routeActive($activePattern));
        }

        return $items;
    }

    /**
     * @param  string|list<string>  $patterns
     */
    private static function routeActive(string|array $patterns): bool
    {
        foreach ((array) $patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{label: string, href: string, active: bool}>  $items
     * @return array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     type: 'group'|'link',
     *     active: bool,
     *     open: bool,
     *     items: list<array{label: string, href: string, active: bool}>,
     *     href?: string
     * }
     */
    private static function group(string $key, string $label, string $icon, array $items): array
    {
        if (count($items) === 1) {
            $only = $items[0];

            return self::leaf($key, $label, $icon, $only['href'], $only['active']);
        }

        $active = collect($items)->contains(fn (array $item) => $item['active']);

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'type' => 'group',
            'active' => $active,
            'open' => $active,
            'items' => $items,
        ];
    }

    /**
     * @return array{key: string, label: string, icon: string, type: 'link', href: string, active: bool, open: bool, items: list<array{label: string, href: string, active: bool}>}
     */
    private static function leaf(string $key, string $label, string $icon, string $href, bool $active): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'type' => 'link',
            'href' => $href,
            'active' => $active,
            'open' => $active,
            'items' => [],
        ];
    }

    /**
     * @return array{label: string, href: string, active: bool}
     */
    private static function link(string $label, string $href, bool $active): array
    {
        return compact('label', 'href', 'active');
    }
}
