<?php

namespace App\Models\Csms;

use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageInvoiceLine extends Model implements BelongsToTenant
{
    use TenantAware;

    protected $fillable = [
        'tenant_id', 'storage_invoice_id', 'storage_lot_id', 'rate_agreement_id',
        'charge_type', 'description', 'quantity', 'unit', 'unit_rate',
        'storage_days', 'free_days', 'grace_days', 'billable_days',
        'amount', 'tax_percent', 'tax_amount', 'line_total', 'is_manual', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StorageInvoice::class, 'storage_invoice_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StorageLot::class, 'storage_lot_id');
    }
}
