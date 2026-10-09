<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_lot_id', 'quantity', 'from_slot_id', 'to_slot_id',
        'status', 'reason', 'created_by', 'approved_by', 'executed_by', 'approved_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function fromSlot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'from_slot_id');
    }

    public function toSlot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'to_slot_id');
    }
}
