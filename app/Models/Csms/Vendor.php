<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'phone', 'email', 'tax_no', 'address', 'status', 'created_by',
    ];

    public function bills(): HasMany
    {
        return $this->hasMany(VendorBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }
}
