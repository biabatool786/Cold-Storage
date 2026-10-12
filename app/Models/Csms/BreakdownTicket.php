<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BreakdownTicket extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'facility_asset_id', 'number', 'description', 'priority',
        'reported_at', 'status', 'assigned_vendor_id', 'assigned_user_id',
        'reported_by', 'closed_by', 'closed_at', 'closure_notes',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
