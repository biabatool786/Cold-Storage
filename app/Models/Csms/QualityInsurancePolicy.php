<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityInsurancePolicy extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'insurer', 'policy_number', 'coverage_type', 'insured_asset',
        'coverage_limit', 'premium_amount', 'starts_on', 'expires_on', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'coverage_limit' => 'decimal:2',
            'premium_amount' => 'decimal:2',
            'starts_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(QualityInsuranceClaim::class);
    }

    public function expiryStatus(): string
    {
        if (! $this->expires_on) {
            return $this->status ?: 'active';
        }
        if ($this->expires_on->lt(now()->startOfDay())) {
            return 'expired';
        }
        if ($this->expires_on->lte(now()->addDays(30)->startOfDay())) {
            return 'expiring_soon';
        }

        return 'active';
    }
}
