<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QualitySopDocument extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'title', 'category', 'current_version', 'status', 'created_by',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(QualitySopVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(QualitySopVersion::class)->where('is_current', true);
    }
}
