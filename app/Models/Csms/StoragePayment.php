<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class StoragePayment extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    protected $fillable = ['tenant_id', 'branch_id', 'storage_invoice_id', 'amount', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }
}
