<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectricityMeter extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'meter_code', 'name', 'location',
        'last_reading', 'last_reading_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_reading' => 'decimal:3',
            'last_reading_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ElectricityMeterReading::class);
    }

    public function chamberMappings(): HasMany
    {
        return $this->hasMany(MeterChamberMapping::class);
    }
}
