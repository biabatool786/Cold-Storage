<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaborLoadingTask extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'task_type', 'reference_type', 'reference_id',
        'reference_label', 'scheduled_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(LaborEmployee::class, 'labor_task_assignments');
    }

    public function workRecords(): HasMany
    {
        return $this->hasMany(LaborWorkRecord::class);
    }
}
