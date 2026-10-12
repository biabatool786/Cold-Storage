<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'default_tax_percent', 'default_free_days', 'default_grace_days',
        'demurrage_rate', 'late_fee_percent', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_tax_percent' => 'decimal:2',
            'demurrage_rate' => 'decimal:2',
            'late_fee_percent' => 'decimal:2',
        ];
    }
}
