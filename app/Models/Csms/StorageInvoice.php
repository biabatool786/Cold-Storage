<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class StorageInvoice extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'customer_id', 'delivery_order_id', 'amount', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payments()
    {
        return $this->hasMany(StoragePayment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
