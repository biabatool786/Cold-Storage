<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class RateAgreement extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'commodity_id', 'rate', 'unit', 'kind',
        'starts_on', 'ends_on', 'status', 'season_status', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
