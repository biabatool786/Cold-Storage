<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Concerns\TenantAware;
use Illuminate\Database\Eloquent\Model;

class RoleAlertPreference extends Model implements BelongsToTenant
{
    use TenantAware;

    public const CATEGORIES = [
        'temperature',
        'humidity',
        'chamber_occupancy',
        'low_stock_dispatch',
        'overdue_invoice',
        'critical_quality',
        'pending_approval',
        'gate',
    ];

    /** @var array<string, list<string>> */
    public const DEFAULT_ROLES = [
        'temperature' => ['Super Admin', 'Admin', 'Manager', 'Supervisor'],
        'humidity' => ['Super Admin', 'Admin', 'Manager', 'Supervisor'],
        'chamber_occupancy' => ['Super Admin', 'Admin', 'Manager', 'Supervisor'],
        'low_stock_dispatch' => ['Super Admin', 'Admin', 'Manager', 'Supervisor'],
        'overdue_invoice' => ['Super Admin', 'Admin', 'Manager', 'Accountant'],
        'critical_quality' => ['Super Admin', 'Admin', 'Manager', 'Supervisor'],
        'pending_approval' => ['Super Admin', 'Admin', 'Manager'],
        'gate' => ['Super Admin', 'Admin', 'Manager', 'Gatekeeper'],
    ];

    protected $fillable = [
        'tenant_id', 'role_name', 'alert_category', 'enabled', 'channels', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'channels' => 'array',
        ];
    }
}
