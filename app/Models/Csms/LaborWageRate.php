<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaborWageRate extends Model implements BelongsToTenant
{
    use BranchScopedRecord, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'labor_employee_id', 'rate_amount', 'rate_unit',
        'effective_from', 'status', 'reason', 'proposed_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'rate_amount' => 'decimal:2',
            'effective_from' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }
}
