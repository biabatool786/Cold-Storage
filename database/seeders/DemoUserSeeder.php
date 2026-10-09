<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Demo accounts on the six documented roles.
     * Former storekeeper, quality, monitoring, and viewer accounts are Supervisors.
     */
    public function run(): void
    {
        app(TenantContext::class)->bypass(function (): void {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => 'demo-tenant'],
                [
                    'name' => 'Demo Tenant',
                    'email' => 'demo-tenant@example.com',
                    'status' => TenantStatus::Active,
                ],
            );

            $branch = Branch::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'MAIN'],
                [
                    'name' => 'Main Branch',
                    'status' => BranchStatus::Active,
                ],
            );

            $accounts = [
                ['superadmin@example.com', 'Super Admin', null, RoleName::SuperAdmin, false],
                ['admin@example.com', 'Admin', $tenant->id, RoleName::Admin, false],
                ['manager@example.com', 'Manager', $tenant->id, RoleName::Manager, true],
                ['quality@example.com', 'Quality Officer', $tenant->id, RoleName::Supervisor, true],
                ['accountant@example.com', 'Accountant', $tenant->id, RoleName::Accountant, true],
                ['gatekeeper@example.com', 'Gatekeeper', $tenant->id, RoleName::Gatekeeper, true],
            ];

            foreach ($accounts as [$email, $name, $tenantId, $role, $assignBranch]) {
                $roleId = Role::query()->where('name', $role->value)->firstOrFail()->id;

                $user = User::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => 'password',
                        'status' => UserStatus::Active,
                        'tenant_id' => $tenantId,
                    ],
                );

                $user->roles()->sync([$roleId]);

                if ($assignBranch) {
                    $user->branches()->sync([$branch->id]);
                }
            }
        });
    }
}
