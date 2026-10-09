<?php

namespace App\Models\Csms;

use App\Models\Branch;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'branch_id', 'stock_count_id', 'storage_lot_id', 'system_qty',
        'counted_qty', 'variance', 'counted_by', 'counted_at',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'variance' => 'decimal:3',
            'counted_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }
}
