<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborWorkRecord extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'labor_loading_task_id', 'labor_employee_id',
        'work_date', 'quantity', 'unit', 'status', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'quantity' => 'decimal:3',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(LaborLoadingTask::class, 'labor_loading_task_id');
    }
}
