<?php

namespace App\Models\Scopes;

use App\Models\Contracts\BelongsToTenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $model instanceof BelongsToTenant) {
            return;
        }

        $context = app(TenantContext::class);

        if ($context->isBypassed() || $context->isSuperAdmin()) {
            return;
        }

        if ($context->id() !== null) {
            $builder->where(
                $model->qualifyColumn($model->getTenantColumn()),
                $context->id(),
            );

            return;
        }

        if ($model::tenantScopeAllowsMissingContext()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
