<?php

namespace App\Support;

use App\Enums\PermissionName;

class WorkspaceCatalog
{
    /**
     * Areas a cold-store role may open. Pages stay empty until that module exists.
     *
     * @return list<array{area: string, label: string, group: string, permission: PermissionName, description: string}>
     */
    public static function areas(): array
    {
        return [
            self::area('customers', 'Customers', 'Operations', PermissionName::CustomersView, 'Depositors who store produce with this cold store.'),
            self::area('products', 'Products', 'Operations', PermissionName::ProductsView, 'Produce accepted into storage.'),
            self::area('varieties', 'Varieties', 'Operations', PermissionName::VarietiesView, 'Varieties recorded against products.'),
            self::area('grades', 'Grades', 'Operations', PermissionName::GradesView, 'Grades used when produce is inspected.'),
            self::area('chambers', 'Chambers', 'Storage', PermissionName::ChambersView, 'Cold rooms and their capacity.'),
            self::area('receiving', 'Receiving', 'Storage', PermissionName::ReceivingView, 'Inbound lots for this tenant.'),
            self::area('batches', 'Batches', 'Storage', PermissionName::BatchesView, 'Stored lots and their identity.'),
            self::area('inventory', 'Inventory', 'Storage', PermissionName::InventoryView, 'Quantity currently on hand.'),
            self::area('transfers', 'Transfers', 'Storage', PermissionName::TransfersView, 'Movement between chambers.'),
            self::area('quality', 'Quality', 'Control', PermissionName::QualityView, 'Inspections, defects, wastage, and shrinkage.'),
            self::area('monitoring', 'Monitoring', 'Control', PermissionName::MonitoringView, 'Temperature, humidity, and chamber alerts.'),
            self::area('billing', 'Billing', 'Finance', PermissionName::BillingView, 'Rate plans, invoices, payments, and balances.'),
            self::area('dispatch', 'Dispatch', 'Movement', PermissionName::DispatchView, 'Outbound loads for this tenant.'),
            self::area('reports', 'Reports', 'Insight', PermissionName::ReportsView, 'Operational or financial summaries for this tenant.'),
        ];
    }

    /**
     * @return array{area: string, label: string, group: string, permission: PermissionName, description: string}|null
     */
    public static function find(string $area): ?array
    {
        foreach (self::areas() as $item) {
            if ($item['area'] === $area) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{area: string, label: string, group: string, permission: PermissionName, description: string}
     */
    private static function area(string $area, string $label, string $group, PermissionName $permission, string $description): array
    {
        return compact('area', 'label', 'group', 'permission', 'description');
    }
}
