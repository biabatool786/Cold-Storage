<?php

namespace App\Services\Security;

use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountSecurity
{
    public function __construct(private readonly AuditLogger $audit, private readonly PasswordPolicy $policy) {}

    public function record(string $action, ?User $user, ?string $email = null): void
    {
        LoginEvent::query()->create([
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'email' => $email ?? $user?->email,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function registerFailure(?User $user, string $email): void
    {
        $this->record('failed_login', $user, $email);
        if (! $user) {
            return;
        }

        $user->increment('failed_login_attempts');
        if ($user->failed_login_attempts >= $this->policy->lockAfter()) {
            $user->update(['locked_at' => now()]);
            $this->record('account_lock', $user->fresh(), $email);
        }
    }

    public function clearFailures(User $user): void
    {
        $user->update(['failed_login_attempts' => 0]);
    }

    public function setStatus(User $actor, User $target, UserStatus $status): void
    {
        $this->guardManaged($actor, $target, 'users.edit');
        $old = $target->status?->value;
        $target->update(['status' => $status]);
        $this->audit->record('user.status_updated', $target, [], $target->tenant_id, [
            'module' => 'users',
            'old' => ['status' => $old],
            'new' => ['status' => $status->value],
        ]);
    }

    public function resetPassword(User $actor, User $target, string $password): void
    {
        $this->guardManaged($actor, $target, 'users.edit');
        $target->update(['password' => $password, 'session_invalidated_at' => now()]);
        DB::table('sessions')->where('user_id', $target->id)->delete();
        $this->audit->record('user.password_reset', $target, ['by' => $actor->id], $target->tenant_id, [
            'module' => 'users',
        ]);
    }

    public function unlock(User $actor, User $target): void
    {
        $this->guardManaged($actor, $target, 'users.execute');
        $target->update(['locked_at' => null, 'failed_login_attempts' => 0]);
        $this->record('unlock', $target);
        $this->audit->record('user.unlocked', $target, [], $target->tenant_id, ['module' => 'users']);
    }

    public function forceLogout(User $actor, User $target): void
    {
        $this->guardManaged($actor, $target, 'users.execute');
        $target->update(['session_invalidated_at' => now()]);
        DB::table('sessions')->where('user_id', $target->id)->delete();
        $this->record('forced_logout', $target);
        $this->audit->record('user.forced_logout', $target, [], $target->tenant_id, ['module' => 'users']);
    }

    public function updateProfile(User $actor, User $target, array $attributes): void
    {
        $this->guardManaged($actor, $target, 'users.edit');
        unset($attributes['tenant_id'], $attributes['branch_id'], $attributes['password'], $attributes['status']);
        $old = $target->only(['name', 'email', 'phone']);
        $target->update($attributes);
        $this->audit->record('user.profile_updated', $target, [], $target->tenant_id, [
            'module' => 'users',
            'old' => $old,
            'new' => $target->only(['name', 'email', 'phone']),
        ]);
    }

    private function guardManaged(User $actor, User $target, string $permission): void
    {
        abort_unless($actor->hasPermission($permission), 403);
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => 'You cannot modify a Super Admin.']);
        }
        if (! $actor->isSuperAdmin() && (int) $actor->tenant_id !== (int) $target->tenant_id) {
            abort(404);
        }
    }
}
