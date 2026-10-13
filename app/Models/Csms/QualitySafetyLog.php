<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualitySafetyLog extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'log_type', 'checked_on', 'equipment_label',
        'chamber_id', 'facility_asset_id', 'result', 'findings', 'follow_up_actions',
        'next_due_on', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->next_due_on && $this->next_due_on->lt(now()->startOfDay());
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }
}
