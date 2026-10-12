<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PmSchedule extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'facility_asset_id', 'maintenance_type', 'frequency_days',
        'next_due_on', 'last_completed_on', 'assigned_vendor_id', 'assigned_user_id',
        'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'next_due_on' => 'date',
            'last_completed_on' => 'date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function dueLabel(): string
    {
        $due = $this->next_due_on ? Carbon::parse($this->next_due_on)->startOfDay() : null;
        if (! $due) {
            return $this->status;
        }
        $today = now()->startOfDay();
        if ($due->lt($today)) {
            return 'overdue';
        }
        if ($due->equalTo($today)) {
            return 'due';
        }

        return 'upcoming';
    }
}
