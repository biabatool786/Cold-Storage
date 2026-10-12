<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'part_code', 'name', 'unit',
        'quantity_on_hand', 'min_stock', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'min_stock' => 'decimal:3',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SparePartMovement::class);
    }
}
