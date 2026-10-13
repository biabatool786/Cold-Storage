<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaborEmployee extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'employee_code', 'name', 'labor_category',
        'engagement_type', 'phone', 'team_code', 'supervisor_id', 'status',
        'is_gate_staff', 'is_visitor_labor', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_gate_staff' => 'boolean',
            'is_visitor_labor' => 'boolean',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function wageRates(): HasMany
    {
        return $this->hasMany(LaborWageRate::class);
    }

    public function currentApprovedRate(): ?LaborWageRate
    {
        return $this->wageRates()
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
