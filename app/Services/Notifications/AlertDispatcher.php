<?php

namespace App\Services\Notifications;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Csms\InternalAlert;
use App\Models\Csms\RoleAlertPreference;
use App\Models\User;
use App\Services\Authorization\BranchAccess;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

class AlertDispatcher
{
    public function __construct(
        private readonly BranchAccess $branches,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{tenant_id: int, branch_id?: ?int, category: string, title: string, body: string, severity?: string, reference_type?: ?string, reference_id?: ?int, idempotency_key: string}  $payload
     * @return list<InternalAlert>
     */
    public function dispatch(array $payload): array
    {
        $category = $payload['category'];
        abort_unless(in_array($category, RoleAlertPreference::CATEGORIES, true), 422);

        return $this->tenant->bypass(function () use ($payload, $category): array {
            $roleNames = $this->rolesForCategory((int) $payload['tenant_id'], $category);
            $users = User::query()
                ->where(function ($q) use ($payload) {
                    $q->where('tenant_id', $payload['tenant_id'])->orWhereNull('tenant_id');
                })
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $roleNames))
                ->where('status', UserStatus::Active->value)
                ->get();

            $created = [];
            foreach ($users as $user) {
                if (! $this->userMayReceive($user, $category)) {
                    continue;
                }
                if (! empty($payload['branch_id'])) {
                    $branch = Branch::query()->find($payload['branch_id']);
                    if ($branch && ! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Admin) && ! $this->branches->allows($user, $branch)) {
                        continue;
                    }
                }

                $key = $payload['idempotency_key'].':u'.$user->id;
                try {
                    $alert = InternalAlert::query()->create([
                        'tenant_id' => $payload['tenant_id'],
                        'branch_id' => $payload['branch_id'] ?? null,
                        'user_id' => $user->id,
                        'category' => $category,
                        'title' => $payload['title'],
                        'body' => $this->sanitizeBody($user, $category, $payload['body']),
                        'severity' => $payload['severity'] ?? 'info',
                        'idempotency_key' => $key,
                        'reference_type' => $payload['reference_type'] ?? null,
                        'reference_id' => $payload['reference_id'] ?? null,
                    ]);
                    $created[] = $alert;
                } catch (QueryException) {
                    // duplicate idempotency — skip
                }
            }

            return $created;
        });
    }

    /**
     * @return list<string>
     */
    public function rolesForCategory(int $tenantId, string $category): array
    {
        $prefs = RoleAlertPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('alert_category', $category)
            ->get();

        if ($prefs->isEmpty()) {
            return RoleAlertPreference::DEFAULT_ROLES[$category] ?? [];
        }

        return $prefs->where('enabled', true)->pluck('role_name')->unique()->values()->all();
    }

    public function userMayReceive(User $user, string $category): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->hasRole(RoleName::Gatekeeper)) {
            return $category === 'gate';
        }
        if ($user->hasRole(RoleName::Accountant) && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            return in_array($category, ['overdue_invoice', 'pending_approval'], true);
        }

        return true;
    }

    private function sanitizeBody(User $user, string $category, string $body): string
    {
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin()) {
            // Strip amounts / customer PII patterns from gate alerts
            $body = preg_replace('/\b\d+(\.\d{2})?\b/', '[restricted]', $body) ?? $body;
            $body = preg_replace('/customer[:\s]+[^\n;]+/i', 'customer: [restricted]', $body) ?? $body;
        }

        return strip_tags($body);
    }
}
