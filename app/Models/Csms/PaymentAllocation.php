<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_payment_id', 'storage_invoice_id', 'amount', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(StoragePayment::class, 'storage_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }
}
