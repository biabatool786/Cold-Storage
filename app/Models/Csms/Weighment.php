<?php

namespace App\Models\Csms;

use App\Enums\RoleName;
use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Weighment extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'gate_entry_id', 'booking_id', 'vehicle_no', 'number', 'kind', 'weight',
        'gross_weight', 'tare_weight', 'net_weight', 'is_manual', 'manual_reason', 'status', 'tolerance_status',
        'created_by', 'approved_by', 'acknowledged_by', 'acknowledged_at', 'acknowledge_comment', 'weighed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'gross_weight' => 'decimal:3',
            'tare_weight' => 'decimal:3',
            'net_weight' => 'decimal:3',
            'is_manual' => 'boolean',
            'acknowledged_at' => 'datetime',
            'weighed_at' => 'datetime',
        ];
    }

    public function gateEntry(): BelongsTo
    {
        return $this->belongsTo(GateEntry::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function editRequests(): HasMany
    {
        return $this->hasMany(WeighmentEditRequest::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Admin)) {
            $query->whereIn('weighments.branch_id', $user->branches()->pluck('branches.id'));
        }
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin()) {
            $query->where('weighments.created_by', $user->id);
        }

        return $query;
    }
}
