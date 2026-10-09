<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignUserRole
{
    public function createUser(User $actor, string $name, string $email, string $password, RoleName $role): User
    {
        $tenantId = $actor->isSuperAdmin() ? null : $actor->tenant_id;

        $this->guard($actor, $tenantId, $role, null);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => UserStatus::Active,
        ]);

        $this->sync($user, $role);

        return $user;
    }

    public function createForTenant(User $actor, Tenant $tenant, string $name, string $email, string $password): User
    {
        $this->guard($actor, $tenant->id, RoleName::Admin, null);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => UserStatus::Active,
        ]);

        $this->sync($user, RoleName::Admin);

        return $user;
    }

    public function assign(User $actor, User $target, RoleName $role): void
    {
        $this->guard(
            $actor,
            $target->tenant_id !== null ? (int) $target->tenant_id : null,
            $role,
            $target,
        );

        $this->sync($target, $role);
    }

    private function guard(User $actor, ?int $tenantId, RoleName $role, ?User $target): void
    {
        if ($target !== null && $actor->is($target)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role or permissions.',
            ]);
        }

        if ($target?->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'You cannot modify a Super Admin.',
            ]);
        }

        if (! $role->canBeAssignedTo($tenantId, $actor)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot assign that role.',
            ]);
        }
    }

    private function sync(User $user, RoleName $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->firstOrFail()->id;

        $user->roles()->sync([$roleId]);
    }
}
