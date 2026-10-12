<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricityMeterReading extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'electricity_meter_id', 'read_at',
        'previous_reading', 'current_reading', 'consumption',
        'is_reset', 'reset_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'consumption' => 'decimal:3',
            'is_reset' => 'boolean',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'electricity_meter_id');
    }
}
