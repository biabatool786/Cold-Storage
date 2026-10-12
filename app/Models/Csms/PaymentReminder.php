<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReminder extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'channel', 'status', 'message', 'outcome', 'created_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
