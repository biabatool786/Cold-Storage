<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'rack_id', 'name', 'code', 'created_by'];

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }
}
