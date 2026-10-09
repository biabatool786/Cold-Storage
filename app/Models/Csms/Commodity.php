<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'variety', 'grade', 'unit', 'status', 'created_by',
        'storage_temperature', 'storage_humidity', 'shelf_life_days', 'shrinkage_percent',
    ];

    protected function casts(): array
    {
        return [
            'storage_temperature' => 'decimal:2',
            'storage_humidity' => 'decimal:2',
            'shrinkage_percent' => 'decimal:3',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function varieties(): HasMany
    {
        return $this->hasMany(CommodityVariety::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(CommodityUnit::class);
    }

    public function incompatibilities(): HasMany
    {
        return $this->hasMany(CommodityIncompatibility::class);
    }

    public function shrinkageChanges(): HasMany
    {
        return $this->hasMany(CommodityShrinkageChange::class);
    }
}
