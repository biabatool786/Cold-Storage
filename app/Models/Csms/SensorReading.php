<?php

namespace App\Models\Csms;

use App\Enums\RoleName;
use App\Models\Concerns\GuardsImmutableRecords;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SensorReading extends Model implements BelongsToTenant
{
    use GuardsImmutableRecords, TenantAware;

    protected $fillable = [
        'tenant_id', 'branch_id', 'chamber_id', 'sensor_id',
        'temperature', 'humidity', 'source', 'notes', 'created_by',
    ];

    public function chamber(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function sensor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MonitoringSensor::class, 'sensor_id');
    }

    public function annotations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MonitoringReadingAnnotation::class, 'sensor_reading_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('sensor_readings.branch_id', $user->branches()->pluck('branches.id'));
    }
}
