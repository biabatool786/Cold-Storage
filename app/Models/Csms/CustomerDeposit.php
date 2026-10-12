<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerDeposit extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'storage_payment_id', 'kind',
        'original_amount', 'used_amount', 'refunded_amount', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(StoragePayment::class, 'storage_payment_id');
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(DepositRefundRequest::class);
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->original_amount - (float) $this->used_amount - (float) $this->refunded_amount);
    }
}
