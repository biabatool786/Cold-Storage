<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoragePayment extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'storage_invoice_id',
        'method', 'reference', 'kind', 'paid_on', 'cheque_number', 'cheque_status', 'cheque_status_at',
        'amount', 'allocated_amount', 'unapplied_amount', 'status',
        'reverse_status', 'reverse_reason', 'reverse_requested_by', 'reverse_approved_by', 'reversed_at',
        'posted_by', 'posted_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'unapplied_amount' => 'decimal:2',
            'paid_on' => 'date',
            'cheque_status_at' => 'datetime',
            'reversed_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null || $this->reverse_status === 'executed';
    }

    public function isFundsEffective(): bool
    {
        if (! $this->isPosted() || $this->isReversed()) {
            return false;
        }
        if ($this->method === 'cheque') {
            return $this->cheque_status === 'cleared';
        }

        return true;
    }

    public function availableToAllocate(): float
    {
        return max(0, (float) $this->amount - (float) $this->allocated_amount);
    }
}
