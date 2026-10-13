<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborOvertime extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $table = 'labor_overtime';

    protected $fillable = [
        'tenant_id', 'branch_id', 'labor_employee_id', 'overtime_date', 'hours',
        'reason', 'status', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'hours' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }
}
