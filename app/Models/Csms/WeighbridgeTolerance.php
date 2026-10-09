<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WeighbridgeTolerance extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = ['tenant_id', 'branch_id', 'limit_kg', 'updated_by'];

    protected function casts(): array
    {
        return ['limit_kg' => 'decimal:3'];
    }
}
