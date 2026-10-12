<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generator extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'generator_code', 'name', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GeneratorLog::class);
    }
}
