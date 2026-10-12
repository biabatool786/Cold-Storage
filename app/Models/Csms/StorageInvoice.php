<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageInvoice extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'delivery_order_id', 'storage_lot_id',
        'billing_bulk_run_id', 'number', 'kind', 'invoice_date', 'due_date',
        'period_start', 'period_end', 'subtotal', 'tax_amount', 'discount_amount',
        'discount_percent', 'discount_reason', 'discount_status', 'discount_approved_by',
        'amount', 'credited_amount', 'debited_amount', 'status', 'posted_at', 'posted_by',
        'cancel_status', 'cancel_reason', 'cancel_requested_by', 'cancel_approved_by',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'credited_amount' => 'decimal:2',
            'debited_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StoragePayment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(BadDebtWriteOff::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StorageInvoiceLine::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BillingAdjustment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['posted', 'locked'], true);
    }

    public function netAmount(): float
    {
        return max(0, (float) $this->amount + (float) $this->debited_amount - (float) $this->credited_amount);
    }

    public function balanceDisplay(): string
    {
        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }
        if ((float) $this->credited_amount > 0 && (float) $this->credited_amount >= (float) $this->amount) {
            return 'Fully credited';
        }
        if ((float) $this->credited_amount > 0) {
            return 'Partially credited';
        }

        return ucfirst($this->status);
    }
}
