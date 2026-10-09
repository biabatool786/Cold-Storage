<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantContextMissingException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait TenantAware
{
    public static function bootTenantAware(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed() || $context->isSuperAdmin()) {
                return;
            }

            $tenantId = $context->id();

            if ($tenantId === null) {
                throw new TenantContextMissingException('A tenant is required to create this record.');
            }

            $model->setAttribute($model->getTenantColumn(), $tenantId);
        });

        static::updating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed() || $context->isSuperAdmin()) {
                return;
            }

            $column = $model->getTenantColumn();

            if ($model->isDirty($column)) {
                $model->setAttribute($column, $model->getOriginal($column));
            }
        });
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * Authentication must be able to load a user before a tenant is known.
     * Other tenant-owned models stay hidden until a tenant or super admin is resolved.
     */
    public static function tenantScopeAllowsMissingContext(): bool
    {
        return false;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
