<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLog extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'maintenance_work_order_id', 'message',
        'status_from', 'status_to', 'created_by',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }
}
