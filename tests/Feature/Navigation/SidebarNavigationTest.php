<?php

namespace Tests\Feature\Navigation;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\Navigation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenant = Tenant::query()->create([
                'name' => 'Nav Co', 'slug' => 'nav-co', 'email' => 'nav@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branch = Branch::query()->create([
                'tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => BranchStatus::Active,
            ]);
        });
    }

    public function test_sidebar_groups_modules_and_respects_permissions(): void
    {
        $manager = $this->user(RoleName::Manager, 'manager@example.com');
        $gatekeeper = $this->user(RoleName::Gatekeeper, 'gate@example.com');
        $accountant = $this->user(RoleName::Accountant, 'accountant@example.com');
        $super = $this->user(RoleName::SuperAdmin, 'super@example.com', null);

        $this->actingAs($manager)->get('/dashboard')->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Dispatch')
            ->assertSee('Users & security')
            ->assertDontSee('Tenants');

        $managerModules = collect(Navigation::modules($manager))->pluck('key');
        $this->assertTrue($managerModules->contains('inventory'));
        $this->assertTrue($managerModules->contains('dispatch'));
        $this->assertTrue($managerModules->contains('monitoring'));
        $this->assertFalse($managerModules->contains('platform'));

        $this->actingAs($gatekeeper)->get('/gates')->assertOk()
            ->assertSee('Gate')
            ->assertDontSee('Invoices')
            ->assertDontSee('Users & security');

        $gkModules = collect(Navigation::modules($gatekeeper))->pluck('key');
        $this->assertTrue($gkModules->contains('gate'));
        $this->assertTrue($gkModules->contains('dispatch'));
        $this->assertFalse($gkModules->contains('finance'));
        $this->assertFalse($gkModules->contains('security'));

        $this->actingAs($accountant)->get('/dashboard')->assertOk()
            ->assertSee('Finance');
        $this->assertFalse(collect(Navigation::modules($accountant))->contains(fn ($m) => $m['key'] === 'gate'));

        $acctModules = collect(Navigation::modules($accountant))->pluck('key');
        $this->assertTrue($acctModules->contains('finance'));
        $this->assertFalse($acctModules->contains('gate'));

        $this->actingAs($super)->get('/dashboard')->assertOk()
            ->assertSee('Platform')
            ->assertSee('Tenants')
            ->assertSee('Users & security');

        $this->actingAs($manager)->get('/inventory')->assertOk()
            ->assertSee('data-module-key="inventory"', false)
            ->assertSee('data-force-open="1"', false);

        foreach (Navigation::modules($manager) as $module) {
            if ($module['type'] === 'link') {
                $this->assertNotEmpty($module['href']);
                continue;
            }
            foreach ($module['items'] as $item) {
                $this->assertNotEmpty($item['href']);
            }
        }
    }

    private function user(RoleName $role, string $email, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;

        return app(TenantContext::class)->bypass(function () use ($role, $email, $tenant): User {
            $user = User::query()->create([
                'tenant_id' => $role === RoleName::SuperAdmin ? null : $tenant?->id,
                'name' => $email,
                'email' => $email,
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);
            $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());
            if ($role !== RoleName::SuperAdmin && $role !== RoleName::Admin) {
                $user->branches()->sync([$this->branch->id]);
            }

            return $user;
        });
    }
}
