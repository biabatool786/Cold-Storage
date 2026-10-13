<?php

namespace App\Models\Csms;

use App\Models\Contracts\BelongsToTenant;
use App\Models\Concerns\TenantAware;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunicationGateway extends Model implements BelongsToTenant
{
    use SoftDeletes, TenantAware;

    protected $fillable = [
        'tenant_id', 'channel', 'provider', 'credentials_encrypted', 'from_address',
        'is_enabled', 'status', 'last_tested_at', 'last_test_result', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
            'credentials_encrypted' => 'encrypted:array',
        ];
    }

    public function publicStatus(): array
    {
        $creds = $this->credentials_encrypted ?? [];

        return [
            'channel' => $this->channel,
            'provider' => $this->provider,
            'from_address' => $this->from_address,
            'is_enabled' => $this->is_enabled,
            'status' => $this->status,
            'last_tested_at' => optional($this->last_tested_at)?->toDateTimeString(),
            'last_test_result' => $this->last_test_result,
            'has_credentials' => filled($creds['api_key'] ?? null) || filled($creds['password'] ?? null) || filled($creds['token'] ?? null),
        ];
    }
}
