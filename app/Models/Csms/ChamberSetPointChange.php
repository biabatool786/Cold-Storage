<?php

namespace App\Models\Csms;

use App\Enums\RoleName;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChamberSetPointChange extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'branch_id', 'chamber_id', 'temperature', 'humidity', 'status', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['temperature' => 'decimal:2', 'humidity' => 'decimal:2'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('chamber_set_point_changes.branch_id', $user->branches()->pluck('branches.id'));
    }
}
