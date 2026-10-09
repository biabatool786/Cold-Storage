<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringCalibration extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'sensor_id', 'calibrated_at',
        'reference_value', 'measured_value', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'calibrated_at' => 'datetime',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(MonitoringSensor::class, 'sensor_id');
    }
}
