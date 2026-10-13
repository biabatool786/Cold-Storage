<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborPayrollRun extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'period_start', 'period_end', 'labor_wage_sheet_id',
        'gross_amount', 'advances_deducted', 'net_payable', 'status',
        'calculation_notes', 'executed_by', 'approved_by', 'approved_at', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'decimal:2',
            'advances_deducted' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function wageSheet(): BelongsTo
    {
        return $this->belongsTo(LaborWageSheet::class, 'labor_wage_sheet_id');
    }
}
