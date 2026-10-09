<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chamber extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'code', 'capacity', 'capacity_unit',
        'set_point', 'humidity_set_point',
        'alarm_temp_min', 'alarm_temp_max', 'alarm_humidity_min', 'alarm_humidity_max',
        'escalation_minutes', 'escalation_role',
        'status', 'created_by',
    ];

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function setPointChanges(): HasMany
    {
        return $this->hasMany(ChamberSetPointChange::class);
    }

    public function sensors(): HasMany
    {
        return $this->hasMany(MonitoringSensor::class);
    }

    public function alarms(): HasMany
    {
        return $this->hasMany(MonitoringAlarm::class);
    }
}
