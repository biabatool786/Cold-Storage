<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Csms\Concerns\ColdStoreRecord;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model implements BelongsToTenant
{
    use ColdStoreRecord;

    public const SUPPORTED_PLACEHOLDERS = [
        'customer_name', 'customer_email', 'customer_phone', 'invoice_number',
        'amount', 'chamber_name', 'branch_name', 'lot_number', 'alert_title', 'alert_body',
    ];

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'event_type', 'channel', 'subject',
        'body', 'placeholders', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
