<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositRefundRequest extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_deposit_id', 'amount', 'reason', 'status',
        'requested_by', 'approved_by', 'executed_by', 'approved_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(CustomerDeposit::class, 'customer_deposit_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
