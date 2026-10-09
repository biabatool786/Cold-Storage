<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'phone', 'email', 'address', 'city', 'tax_no',
        'billing_name', 'billing_address', 'credit_limit', 'status', 'hold_reason',
        'status_changed_at', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2'];
    }

    public function rateAgreements(): HasMany
    {
        return $this->hasMany(RateAgreement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function creditChanges(): HasMany
    {
        return $this->hasMany(CreditLimitChange::class);
    }
}
