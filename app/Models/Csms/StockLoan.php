<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLoan extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'storage_lot_id', 'amount', 'outstanding_amount',
        'status', 'notes', 'created_by', 'approved_by', 'closed_by', 'approved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
