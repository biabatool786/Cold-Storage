<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chamber extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'code', 'capacity', 'capacity_unit',
        'set_point', 'humidity_set_point', 'status', 'created_by',
    ];

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function setPointChanges(): HasMany
    {
        return $this->hasMany(ChamberSetPointChange::class);
    }
}
