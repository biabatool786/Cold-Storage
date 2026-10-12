<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeakLoadThreshold extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'electricity_meter_id', 'threshold_kw',
        'status', 'proposed_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'threshold_kw' => 'decimal:3',
            'approved_at' => 'datetime',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'electricity_meter_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PeakLoadAlert::class);
    }
}
