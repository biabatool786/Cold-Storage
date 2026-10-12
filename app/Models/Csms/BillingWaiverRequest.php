<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingWaiverRequest extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_invoice_id', 'amount', 'reason',
        'status', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }
}
