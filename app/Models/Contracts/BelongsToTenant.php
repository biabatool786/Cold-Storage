<?php

namespace App\Models\Contracts;

interface BelongsToTenant
{
    public function getTenantColumn(): string;

    public static function tenantScopeAllowsMissingContext(): bool;
}
