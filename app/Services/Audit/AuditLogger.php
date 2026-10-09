<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $subject = null, array $properties = [], ?int $tenantId = null, array $context = []): AuditLog
    {
        $actor = Auth::user();
        $roleName = $context['role'] ?? null;

        if ($roleName === null && $actor instanceof User) {
            $actor->loadMissing('roles');
            $roleName = $actor->roles->pluck('name')->first();
        }

        return AuditLog::query()->create([
            'tenant_id' => $tenantId ?? ($actor instanceof User ? $actor->tenant_id : null),
            'user_id' => $actor instanceof User ? $actor->id : null,
            'role_name' => $roleName,
            'branch_id' => $context['branch_id'] ?? null,
            'action' => $action,
            'module' => $context['module'] ?? null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'old_values' => $context['old'] ?? null,
            'new_values' => $context['new'] ?? null,
            'reason' => $context['reason'] ?? null,
            'ip_address' => $context['ip'] ?? Request::ip(),
            'user_agent' => $context['user_agent'] ?? Request::userAgent(),
        ]);
    }
}
