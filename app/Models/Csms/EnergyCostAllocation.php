<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnergyCostAllocation extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'period_start', 'period_end',
        'energy_cost', 'kwh_total', 'allocation_basis', 'calculation_notes',
        'status', 'storage_invoice_id', 'billing_adjustment_id',
        'proposed_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'energy_cost' => 'decimal:2',
            'kwh_total' => 'decimal:3',
            'approved_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(BillingAdjustment::class, 'billing_adjustment_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
