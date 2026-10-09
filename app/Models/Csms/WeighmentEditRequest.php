<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeighmentEditRequest extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'branch_id', 'weighment_id', 'gross_weight', 'tare_weight',
        'reason', 'status', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['gross_weight' => 'decimal:3', 'tare_weight' => 'decimal:3'];
    }

    public function weighment(): BelongsTo
    {
        return $this->belongsTo(Weighment::class);
    }
}
