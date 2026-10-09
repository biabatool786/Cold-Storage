<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BelongsToTenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->tenant_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->tenant_id !== null;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    protected function sameTenant(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = $model->getAttribute('tenant_id');

        return $user->tenant_id !== null
            && $tenantId !== null
            && (int) $user->tenant_id === (int) $tenantId;
    }
}
