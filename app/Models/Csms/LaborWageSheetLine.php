<?php

namespace App\Models\Csms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborWageSheetLine extends Model
{
    protected $fillable = [
        'labor_wage_sheet_id', 'labor_employee_id', 'days_present', 'work_quantity',
        'rate_amount', 'line_amount', 'source_notes',
    ];

    protected function casts(): array
    {
        return [
            'days_present' => 'decimal:2',
            'work_quantity' => 'decimal:3',
            'rate_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(LaborWageSheet::class, 'labor_wage_sheet_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }
}
