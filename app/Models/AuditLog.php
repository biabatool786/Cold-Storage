<?php

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use App\Models\Concerns\GuardsImmutableRecords;
use App\Models\Concerns\TenantAware;
use App\Models\Contracts\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model implements BelongsToTenant
{
    use GuardsImmutableRecords, TenantAware;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_name',
        'branch_id',
        'action',
        'module',
        'subject_type',
        'subject_id',
        'properties',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function restore(): mixed
    {
        throw new ImmutableRecordException('Audit logs cannot be restored or changed.');
    }
}
