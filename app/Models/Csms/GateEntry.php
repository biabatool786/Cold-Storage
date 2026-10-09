<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GateEntry extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'booking_id', 'gate_pass_id', 'vehicle_no', 'driver_name',
        'purpose', 'status', 'entered_at', 'exited_at', 'token', 'seal_number', 'vehicle_condition',
        'photo_path', 'pass_verified_at', 'override_approved_by', 'override_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'exited_at' => 'datetime', 'pass_verified_at' => 'datetime'];
    }

    public function weighments(): HasMany
    {
        return $this->hasMany(Weighment::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
