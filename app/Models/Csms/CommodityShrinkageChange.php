<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommodityShrinkageChange extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'commodity_id', 'percent', 'status', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:3'];
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }
}
