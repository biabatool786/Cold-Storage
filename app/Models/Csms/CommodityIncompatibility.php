<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommodityIncompatibility extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'commodity_id', 'incompatible_commodity_id', 'created_by'];

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    public function other(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'incompatible_commodity_id');
    }
}
