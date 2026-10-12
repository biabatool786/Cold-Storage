<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceWorkOrder extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'facility_asset_id', 'breakdown_ticket_id', 'pm_schedule_id',
        'number', 'kind', 'description', 'assigned_vendor_id', 'assigned_user_id',
        'planned_start', 'planned_end', 'estimated_cost', 'actual_cost',
        'cost_status', 'cost_requested_by', 'cost_approved_by', 'status',
        'completion_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'planned_start' => 'date',
            'planned_end' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(BreakdownTicket::class, 'breakdown_ticket_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PmSchedule::class, 'pm_schedule_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WorkOrderLog::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SparePartMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
