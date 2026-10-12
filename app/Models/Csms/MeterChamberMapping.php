<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterChamberMapping extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'electricity_meter_id', 'chamber_id',
        'effective_from', 'effective_to', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'electricity_meter_id');
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }
}
