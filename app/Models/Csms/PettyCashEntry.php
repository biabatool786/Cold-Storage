<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashEntry extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'kind', 'entry_date', 'amount', 'reference', 'notes',
        'operating_expense_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(OperatingExpense::class, 'operating_expense_id');
    }
}
