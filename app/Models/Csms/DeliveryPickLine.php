<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPickLine extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'delivery_order_id', 'storage_lot_id', 'slot_id', 'suggested_qty',
        'picked_qty', 'sort_order', 'is_override', 'status', 'picked_by', 'picked_at',
    ];

    protected function casts(): array
    {
        return [
            'suggested_qty' => 'decimal:3',
            'picked_qty' => 'decimal:3',
            'is_override' => 'boolean',
            'picked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
