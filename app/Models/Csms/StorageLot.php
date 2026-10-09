<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StorageLot extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'goods_receipt_id', 'chamber_id', 'slot_id', 'customer_id',
        'commodity_id', 'commodity_variety_id', 'commodity_grade_id', 'lot_number', 'barcode',
        'quantity', 'held_qty', 'quarantined_qty', 'dispatched_qty', 'status', 'hold_status',
        'received_at', 'expires_on',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'held_qty' => 'decimal:3',
            'quarantined_qty' => 'decimal:3',
            'dispatched_qty' => 'decimal:3',
            'received_at' => 'datetime',
            'expires_on' => 'date',
        ];
    }

    public function availableQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->held_qty - (float) $this->quarantined_qty);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CommodityVariety::class, 'commodity_variety_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(CommodityGrade::class, 'commodity_grade_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function putAwayTask(): HasOne
    {
        return $this->hasOne(PutAwayTask::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class);
    }
}
