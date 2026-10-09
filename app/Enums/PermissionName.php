<?php

namespace App\Enums;

enum PermissionName: string
{
    case DashboardView = 'dashboard.view';
    case ProfileUpdate = 'profile.update';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case TenantsView = 'tenants.view';
    case TenantsManage = 'tenants.manage';
    case SubscriptionsView = 'subscriptions.view';
    case PlatformSettings = 'platform.settings';
    case TenantSettings = 'tenant.settings';
    case AuditView = 'audit.view';
    case PlatformReports = 'platform.reports';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ProductsView = 'products.view';
    case VarietiesView = 'varieties.view';
    case GradesView = 'grades.view';
    case GradesManage = 'grades.manage';
    case ChambersView = 'chambers.view';
    case ReceivingView = 'receiving.view';
    case ReceivingManage = 'receiving.manage';
    case BatchesView = 'batches.view';
    case BatchesManage = 'batches.manage';
    case InventoryView = 'inventory.view';
    case TransfersView = 'transfers.view';
    case TransfersManage = 'transfers.manage';
    case QualityView = 'quality.view';
    case QualityManage = 'quality.manage';
    case MonitoringView = 'monitoring.view';
    case MonitoringAcknowledge = 'monitoring.acknowledge';
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';
    case DispatchView = 'dispatch.view';
    case DispatchManage = 'dispatch.manage';
    case ReportsView = 'reports.view';

    public function description(): string
    {
        return str_replace('.', ' ', $this->value);
    }
}
