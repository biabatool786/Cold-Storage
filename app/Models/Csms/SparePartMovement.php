<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SparePartMovement extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'spare_part_id', 'kind', 'quantity', 'balance_after',
        'maintenance_work_order_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }
}
