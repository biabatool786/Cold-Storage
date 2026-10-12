<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeakLoadAlert extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'electricity_meter_id', 'peak_load_threshold_id',
        'electricity_meter_reading_id', 'alerted_at', 'measured_load',
        'threshold_kw', 'status', 'source',
    ];

    protected function casts(): array
    {
        return [
            'alerted_at' => 'datetime',
            'measured_load' => 'decimal:3',
            'threshold_kw' => 'decimal:3',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'electricity_meter_id');
    }

    public function threshold(): BelongsTo
    {
        return $this->belongsTo(PeakLoadThreshold::class, 'peak_load_threshold_id');
    }
}
