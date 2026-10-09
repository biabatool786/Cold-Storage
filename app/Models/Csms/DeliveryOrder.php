<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'storage_lot_id', 'customer_id', 'quantity', 'dues_amount', 'status', 'created_by', 'approved_by'];

    protected function casts(): array
    {
        return ['dues_amount' => 'decimal:2', 'quantity' => 'decimal:3'];
    }

    public function gatePasses()
    {
        return $this->hasMany(GatePass::class);
    }

    public function lot()
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
