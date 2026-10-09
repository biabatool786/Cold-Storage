<?php

namespace App\Models\Csms;

use App\Models\Branch;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use App\Models\User;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedgerEntry extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'branch_id', 'storage_lot_id', 'type', 'quantity', 'balance_after', 'reason',
        'reference_type', 'reference_id', 'from_slot_id', 'to_slot_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
