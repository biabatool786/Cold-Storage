<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\BranchScopedRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AssetContract extends Model implements BelongsToTenant
{
    use BranchScopedRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'facility_asset_id', 'kind', 'provider_name', 'vendor_id', 'reference_no',
        'starts_on', 'ends_on', 'coverage_notes', 'cost', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'facility_asset_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function expiryLabel(): string
    {
        $end = Carbon::parse($this->ends_on)->startOfDay();
        $today = now()->startOfDay();
        if ($end->lt($today)) {
            return 'expired';
        }
        if ($end->lte($today->copy()->addDays(30))) {
            return 'expiring';
        }

        return 'active';
    }
}
