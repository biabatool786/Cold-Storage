<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $supervisorA;

    private User $gatekeeperA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = $this->tenant('Tenant A', 'tenant-a');
            $this->tenantB = $this->tenant('Tenant B', 'tenant-b');
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-a@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-a@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-a@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-a@example.com');
            $this->user($this->tenantB, RoleName::Admin, 'admin-b@example.com');
        });
    }

    public function test_super_admin_manages_tenants_without_tenant_scope(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/tenants')
            ->assertOk()
            ->assertSee('Tenant A')
            ->assertSee('Tenant B');

        $this->actingAs($this->superAdmin)
            ->post('/tenants', [
                'name' => 'North Store',
                'email' => 'north@example.com',
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertRedirect();

        $created = Tenant::query()->where('email', 'north@example.com')->first();
        $this->assertNotNull($created);
        $this->assertSame(TenantStatus::Active, $created->status);

        $this->actingAs($this->superAdmin)
            ->put('/tenants/'.$created->id.'/status', ['status' => TenantStatus::Suspended->value])
            ->assertRedirect();

        $this->assertSame(TenantStatus::Suspended, $created->fresh()->status);

        $this->actingAs($this->superAdmin)
            ->post('/tenants/'.$this->tenantA->id.'/admins', [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertRedirect();

        $admin = User::query()->where('email', 'new-admin@example.com')->first();
        $this->assertSame($this->tenantA->id, $admin->tenant_id);
        $this->assertTrue($admin->hasRole(RoleName::Admin));

        $this->actingAs($this->superAdmin)->get('/reports')->assertOk()->assertSee('Platform reports');
        $this->actingAs($this->superAdmin)->get('/audit-logs')->assertOk()->assertSee('tenant.created');
    }

    public function test_tenant_admin_is_isolated_and_cannot_manage_the_platform(): void
    {
        $this->actingAs($this->adminA)->get('/tenants')->assertForbidden();
        $this->actingAs($this->adminA)->post('/tenants', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
        ])->assertForbidden();
        $this->actingAs($this->adminA)->get('/tenants/'.$this->tenantB->id)->assertNotFound();
        $this->actingAs($this->adminA)->put('/tenants/'.$this->tenantB->id.'/status', [
            'status' => TenantStatus::Suspended->value,
        ])->assertNotFound();

        $this->actingAs($this->adminA)
            ->put('/users/'.$this->managerA->id.'/role', ['role' => RoleName::Admin->value])
            ->assertSessionHasErrors('role');

        $this->actingAs($this->adminA)->get('/customers')->assertOk();
        $this->actingAs($this->adminA)->get('/settings')->assertOk()->assertSee('Cold storage settings');
        $this->actingAs($this->adminA)->put('/settings', [
            'platform_name' => 'Hijack',
            'support_email' => 'hijack@example.com',
        ])->assertForbidden();
    }

    public function test_manager_permissions_stop_at_operations(): void
    {
        $this->actingAs($this->managerA)->get('/dashboard')->assertOk()->assertSee('Pending Approvals');
        $this->actingAs($this->managerA)->get('/customers')->assertOk();
        $this->actingAs($this->managerA)->get('/grns')->assertOk();
        $this->actingAs($this->managerA)->get('/invoices')->assertOk();
        $this->actingAs($this->managerA)->get('/approval-limits')->assertForbidden();
        $this->actingAs($this->managerA)->get('/settings')->assertForbidden();
        $this->actingAs($this->managerA)->get('/tenants')->assertForbidden();
        $this->actingAs($this->managerA)->post('/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => RoleName::Supervisor->value,
        ])->assertForbidden();
    }

    public function test_supervisor_and_gatekeeper_permissions(): void
    {
        $this->actingAs($this->supervisorA)->get('/grns')->assertOk();
        $this->actingAs($this->supervisorA)->get('/quality-checks')->assertOk();
        $this->actingAs($this->supervisorA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/settings')->assertForbidden();
        $this->actingAs($this->supervisorA)
            ->post('/monitoring/acknowledge')
            ->assertRedirect();

        $this->actingAs($this->gatekeeperA)->get('/delivery-orders')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/lots')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/users')->assertForbidden();
        $this->actingAs($this->gatekeeperA)
            ->post('/monitoring/acknowledge')
            ->assertForbidden();
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'status' => TenantStatus::Active,
        ]);
    }

    private function user(?Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}
