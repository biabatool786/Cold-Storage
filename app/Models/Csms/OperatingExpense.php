<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperatingExpense extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'kind', 'category', 'expense_date', 'chamber_id', 'customer_id', 'vendor_id',
        'description', 'amount', 'payment_method', 'reference', 'status', 'rejection_reason',
        'created_by', 'approved_by', 'approved_at', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'posted'], true);
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }
}
