<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingBulkRun extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'period_start', 'period_end', 'status',
        'summary', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'summary' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(StorageInvoice::class, 'billing_bulk_run_id');
    }
}
