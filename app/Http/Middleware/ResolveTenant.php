<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->discardSubmittedTenantId($request);

        $user = $request->user();

        if ($user instanceof User && $user->isSuperAdmin()) {
            $this->tenantContext->resolveAsSuperAdmin();
        } elseif ($user instanceof User && $user->tenant_id !== null) {
            $this->tenantContext->resolveForTenant((int) $user->tenant_id);
        } else {
            $this->tenantContext->resolveAsGuest();
        }

        return $next($request);
    }

    private function discardSubmittedTenantId(Request $request): void
    {
        foreach (['tenant_id', 'branch_id'] as $key) {
            $request->request->remove($key);
        }

        $query = $request->query->all();
        unset($query['tenant_id'], $query['branch_id']);
        $request->query->replace($query);

        if ($request->isJson()) {
            $payload = $request->json()->all();
            unset($payload['tenant_id'], $payload['branch_id']);
            $request->json()->replace($payload);
        }
    }
}
