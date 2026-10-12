<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAdjustment extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_invoice_id', 'kind', 'number',
        'amount', 'reason', 'status', 'created_by', 'approved_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }
}
