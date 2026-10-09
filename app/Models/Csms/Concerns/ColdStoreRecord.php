<?php

namespace App\Models\Csms\Concerns;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Concerns\TenantAware;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

trait ColdStoreRecord
{
    use SoftDeletes, TenantAware;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        if (! in_array('branch_id', $this->getFillable(), true)) {
            return $query;
        }

        return $query->whereIn(
            $this->getTable().'.branch_id',
            $user->branches()->pluck('branches.id'),
        );
    }
}
