<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QualityChecklistTemplate extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'category', 'applicable_process',
        'instructions', 'is_active', 'current_version', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QualityChecklistVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(QualityChecklistVersion::class)->latestOfMany('version_number');
    }
}
