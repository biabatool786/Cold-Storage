<?php

namespace App\Services\Tenancy;

class TenantContext
{
    private bool $resolved = false;

    private bool $superAdmin = false;

    private ?int $tenantId = null;

    private int $bypassDepth = 0;

    public function resolveForTenant(int $tenantId): void
    {
        $this->resolved = true;
        $this->superAdmin = false;
        $this->tenantId = $tenantId;
    }

    public function resolveAsSuperAdmin(): void
    {
        $this->resolved = true;
        $this->superAdmin = true;
        $this->tenantId = null;
    }

    public function resolveAsGuest(): void
    {
        $this->resolved = true;
        $this->superAdmin = false;
        $this->tenantId = null;
    }

    public function forget(): void
    {
        $this->resolved = false;
        $this->superAdmin = false;
        $this->tenantId = null;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function isSuperAdmin(): bool
    {
        return $this->resolved && $this->superAdmin;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Run trusted server code without tenant filtering or forced tenant assignment.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function bypass(callable $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }
}
