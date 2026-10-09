<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommodityUnit extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'commodity_id', 'unit', 'factor_to_kg'];

    protected function casts(): array
    {
        return ['factor_to_kg' => 'decimal:3'];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}
