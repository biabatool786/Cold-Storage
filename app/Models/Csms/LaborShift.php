<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LaborShift extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'shift_date', 'starts_at', 'ends_at',
        'team_code', 'created_by',
    ];

    protected function casts(): array
    {
        return ['shift_date' => 'date'];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(LaborEmployee::class, 'labor_shift_assignments');
    }
}
