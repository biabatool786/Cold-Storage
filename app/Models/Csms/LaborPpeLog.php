<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborPpeLog extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'labor_employee_id', 'item_or_training', 'log_type',
        'logged_on', 'quantity', 'expires_on', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'expires_on' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(LaborEmployee::class, 'labor_employee_id');
    }
}
