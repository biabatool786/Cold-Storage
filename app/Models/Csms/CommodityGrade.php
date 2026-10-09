<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommodityGrade extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'commodity_variety_id', 'name', 'code', 'created_by'];

    public function variety(): BelongsTo
    {
        return $this->belongsTo(CommodityVariety::class, 'commodity_variety_id');
    }
}
