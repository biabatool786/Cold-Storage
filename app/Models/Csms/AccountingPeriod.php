<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'period_start', 'period_end', 'status',
        'close_requested_by', 'close_approved_by', 'closed_at',
        'reopen_reason', 'reopened_by', 'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
