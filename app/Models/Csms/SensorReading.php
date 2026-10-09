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

    protected $fillable = ['tenant_id', 'branch_id', 'chamber_id', 'temperature', 'humidity', 'created_by'];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('sensor_readings.branch_id', $user->branches()->pluck('branches.id'));
    }
}
