<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHold extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_lot_id', 'kind', 'quantity', 'status', 'reason',
        'created_by', 'approved_by', 'released_by', 'activated_at', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'activated_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }
}
