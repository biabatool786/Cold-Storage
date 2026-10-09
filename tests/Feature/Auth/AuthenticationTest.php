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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private User $viewerA;

    private User $managerA;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'email' => 'tenant-a@example.com',
                'status' => TenantStatus::Active,
            ]);

            $this->tenantB = Tenant::query()->create([
                'name' => 'Tenant B',
                'slug' => 'tenant-b',
                'email' => 'tenant-b@example.com',
                'status' => TenantStatus::Active,
            ]);

            $this->adminA = $this->userFor($this->tenantA, RoleName::Admin, 'admin-a@example.com', 'Admin A');
            $this->adminB = $this->userFor($this->tenantB, RoleName::Admin, 'admin-b@example.com', 'Admin B');
            $this->viewerA = $this->userFor($this->tenantA, RoleName::Gatekeeper, 'viewer-a@example.com', 'Gatekeeper A');
            $this->managerA = $this->userFor($this->tenantA, RoleName::Manager, 'manager-a@example.com', 'Manager A');
            $this->superAdmin = $this->userFor(null, RoleName::SuperAdmin, 'super-admin@example.com', 'Super Admin');
        });
    }

    public function test_login_and_logout(): void
    {
        $this->post('/login', [
            'email' => 'admin-a@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->adminA);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Admin A')
            ->assertSee('Admin');

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->post('/login', [
            'email' => 'admin-a@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_and_platform_users_without_super_admin_cannot_sign_in(): void
    {
        app(TenantContext::class)->bypass(function (): void {
            $this->userFor($this->tenantA, RoleName::Gatekeeper, 'suspended@example.com', 'Suspended', UserStatus::Suspended);
            User::query()->create([
                'name' => 'Orphan',
                'email' => 'orphan@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);
        });

        $this->post('/login', [
            'email' => 'suspended@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => 'orphan@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_reset_replaces_the_password(): void
    {
        Notification::fake();

        $this->post('/forgot-password', [
            'email' => 'admin-a@example.com',
        ])->assertSessionHas('status');

        Notification::assertSentTo($this->adminA, ResetPassword::class);

        $token = Password::broker()->createToken($this->adminA);

        $this->get('/reset-password/'.$token.'?email=admin-a@example.com')
            ->assertOk()
            ->assertSee('Reset password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin-a@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => 'admin-a@example.com',
            'password' => 'new-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('new-password', $this->adminA->fresh()->password));
    }

    public function test_profile_update_ignores_submitted_tenant_id(): void
    {
        $this->actingAs($this->adminA)
            ->put('/profile', [
                'name' => 'Renamed Admin',
                'email' => 'admin-a@example.com',
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertRedirect(route('profile.edit'));

        $fresh = $this->adminA->fresh();

        $this->assertSame('Renamed Admin', $fresh->name);
        $this->assertSame($this->tenantA->id, $fresh->tenant_id);
    }

    public function test_role_authorization_restricts_user_management(): void
    {
        $this->actingAs($this->viewerA)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(route('users.index'), false);

        $this->actingAs($this->viewerA)
            ->get('/users')
            ->assertForbidden();

        $this->actingAs($this->managerA)
            ->get('/users')
            ->assertOk()
            ->assertDontSee('Create user');

        $this->actingAs($this->managerA)
            ->post('/users', $this->newUserPayload())
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->get('/users')
            ->assertOk()
            ->assertSee('Create user')
            ->assertSee('Users');
    }

    public function test_tenant_users_cannot_access_another_tenant(): void
    {
        $this->actingAs($this->adminA)
            ->get('/users')
            ->assertOk()
            ->assertSee('admin-a@example.com')
            ->assertDontSee('admin-b@example.com');

        $this->actingAs($this->adminA)
            ->put('/users/'.$this->adminB->id.'/role', [
                'role' => RoleName::Gatekeeper->value,
            ])
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->post('/users', $this->newUserPayload([
                'tenant_id' => $this->tenantB->id,
                'role' => RoleName::Supervisor->value,
            ]))
            ->assertRedirect(route('users.index'));

        app(TenantContext::class)->resolveForTenant($this->tenantA->id);

        $created = User::query()->where('email', 'new-user@example.com')->first();

        $this->assertNotNull($created);
        $this->assertSame($this->tenantA->id, $created->tenant_id);
        $this->assertTrue($created->hasRole(RoleName::Supervisor));
        $this->assertFalse($created->isSuperAdmin());

        app(TenantContext::class)->resolveForTenant($this->tenantB->id);

        $this->assertNull(User::query()->where('email', 'new-user@example.com')->first());
    }

    public function test_super_admin_is_not_restricted_by_tenant_scope(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/users')
            ->assertOk()
            ->assertSee('admin-a@example.com')
            ->assertSee('admin-b@example.com')
            ->assertSee('Platform');

        $this->actingAs($this->superAdmin)
            ->put('/users/'.$this->viewerA->id.'/role', [
                'role' => RoleName::Accountant->value,
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertRedirect(route('users.index'));

        $viewer = $this->viewerA->fresh();

        $this->assertTrue($viewer->hasRole(RoleName::Accountant));
        $this->assertSame($this->tenantA->id, $viewer->tenant_id);
        $this->assertNull($this->superAdmin->fresh()->tenant_id);
    }

    public function test_guests_cannot_reach_authenticated_pages(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_tenant_user_with_super_admin_role_name_stays_inside_the_tenant(): void
    {
        app(TenantContext::class)->bypass(function (): void {
            $this->viewerA->roles()->sync([
                Role::query()->where('name', RoleName::SuperAdmin->value)->firstOrFail()->id,
            ]);
        });

        $this->assertFalse($this->viewerA->fresh()->isSuperAdmin());

        $this->actingAs($this->viewerA->fresh())
            ->get('/users')
            ->assertForbidden();

        $this->actingAs($this->viewerA->fresh())
            ->get('/dashboard')
            ->assertForbidden();

        app(TenantContext::class)->resolveForTenant($this->tenantA->id);

        $this->assertNull(User::query()->find($this->adminB->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newUserPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => RoleName::Manager->value,
        ], $overrides);
    }

    private function userFor(?Tenant $tenant, RoleName $role, string $email, string $name, UserStatus $status = UserStatus::Active): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'status' => $status,
        ]);

        $user->roles()->attach(
            Role::query()->where('name', $role->value)->firstOrFail(),
        );

        return $user;
    }
}
