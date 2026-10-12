<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'operating_expense_id', 'original_name', 'path', 'mime', 'size', 'created_by',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(OperatingExpense::class, 'operating_expense_id');
    }
}
