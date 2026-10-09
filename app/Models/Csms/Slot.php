<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slot extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'chamber_id', 'rack_id', 'level_id', 'code', 'status',
        'blocked_by', 'blocked_at', 'block_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime'];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function availableForStorage(): bool
    {
        return $this->status === 'available';
    }
}
