<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionMatrixController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('users.edit'), 403);

        return view('security.matrix', [
            'roles' => $this->editableRoles($request->user()),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->hasPermission('users.edit'), 403);
        $data = $request->validate([
            'grants' => ['array'],
            'grants.*' => ['array'],
            'grants.*.*' => ['string'],
        ]);

        $blocked = ['tenants.view', 'tenants.manage', 'platform.settings', 'platform.reports', 'approval_limits.edit', 'records.restore', 'subscriptions.view', 'users.manage'];
        foreach ($this->editableRoles($actor) as $role) {
            if ($role->name === RoleName::SuperAdmin->value || ! array_key_exists($role->id, $data['grants'] ?? [])) {
                continue;
            }
            $names = array_values(array_filter($data['grants'][$role->id] ?? []));
            if (! $actor->isSuperAdmin()) {
                $names = array_values(array_diff($names, $blocked));
            }
            $ids = Permission::query()->whereIn('name', $names)->pluck('id');
            $role->permissions()->sync($ids);
        }

        $audit->record('roles.matrix_updated', null, [], $actor->tenant_id, ['module' => 'users']);

        return back()->with('status', 'Permission matrix saved.');
    }

    /**
     * @return Collection<int, Role>
     */
    private function editableRoles(User $actor): Collection
    {
        $names = $actor->isSuperAdmin()
            ? array_map(fn (RoleName $role) => $role->value, RoleName::cases())
            : [RoleName::Manager->value, RoleName::Supervisor->value, RoleName::Gatekeeper->value, RoleName::Accountant->value];

        return Role::query()->with('permissions')->whereIn('name', $names)->orderBy('name')->get();
    }
}
