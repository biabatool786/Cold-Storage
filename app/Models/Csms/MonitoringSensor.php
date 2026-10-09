<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoringSensor extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'chamber_id', 'code', 'name', 'type', 'status',
        'config', 'last_seen_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(MonitoringCalibration::class, 'sensor_id');
    }

    public function isLiveAvailable(): bool
    {
        return $this->status === 'active' && $this->last_seen_at !== null;
    }
}
