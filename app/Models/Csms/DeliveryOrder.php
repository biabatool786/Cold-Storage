<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryOrder extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_lot_id', 'customer_id', 'commodity_id', 'number', 'quantity',
        'picked_qty', 'dispatched_qty', 'remaining_qty', 'dues_amount', 'stock_ok', 'credit_ok', 'has_dues_flag',
        'pick_mode', 'fifo_override', 'fifo_override_reason', 'fifo_override_by', 'outgoing_temperature',
        'loader_count', 'loader_notes', 'loading_verified', 'loading_verified_by', 'loading_verified_at',
        'status', 'created_by', 'approved_by', 'dues_recommended_by', 'dues_recommend_note',
        'cancel_reason', 'cancelled_by', 'cancelled_at', 'last_edit_reason',
    ];

    protected function casts(): array
    {
        return [
            'dues_amount' => 'decimal:2',
            'quantity' => 'decimal:3',
            'picked_qty' => 'decimal:3',
            'dispatched_qty' => 'decimal:3',
            'remaining_qty' => 'decimal:3',
            'stock_ok' => 'boolean',
            'credit_ok' => 'boolean',
            'has_dues_flag' => 'boolean',
            'fifo_override' => 'boolean',
            'loading_verified' => 'boolean',
            'outgoing_temperature' => 'decimal:2',
            'loading_verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function gatePasses(): HasMany
    {
        return $this->hasMany(GatePass::class);
    }

    public function pass(): HasOne
    {
        return $this->hasOne(GatePass::class)->latestOfMany();
    }

    public function pickLines(): HasMany
    {
        return $this->hasMany(DeliveryPickLine::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(DispatchReturn::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function isEditableDraft(): bool
    {
        return in_array($this->status, ['draft', 'pending'], true);
    }
}
