<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityAsset extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'asset_code', 'name', 'title', 'category', 'location',
        'serial_number', 'installed_at', 'operational_status', 'purchase_cost', 'chamber_id',
        'detail', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
            'installed_at' => 'date',
        ];
    }

    public function chamber(): BelongsTo
    {
        return $this->belongsTo(Chamber::class);
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(PmSchedule::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(BreakdownTicket::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(AssetContract::class);
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->title ?: ('Asset #'.$this->id));
    }
}
