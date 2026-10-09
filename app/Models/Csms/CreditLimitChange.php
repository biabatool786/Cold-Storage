<?php

namespace App\Models\Csms;

use App\Enums\RoleName;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CreditLimitChange extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'branch_id', 'customer_id', 'amount', 'status', 'created_by', 'approved_by', 'reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('credit_limit_changes.branch_id', $user->branches()->pluck('branches.id'));
    }
}
