<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function demoAccounts(): array
    {
        return [
            'Super Admin' => ['superadmin@example.com', RoleName::SuperAdmin->value, true],
            'Admin' => ['admin@example.com', RoleName::Admin->value, false],
            'Manager' => ['manager@example.com', RoleName::Manager->value, false],
            'Supervisor' => ['quality@example.com', RoleName::Supervisor->value, false],
            'Accountant' => ['accountant@example.com', RoleName::Accountant->value, false],
            'Gatekeeper' => ['gatekeeper@example.com', RoleName::Gatekeeper->value, false],
        ];
    }

    #[DataProvider('demoAccounts')]
    public function test_login_redirects_to_the_dashboard(string $email, string $role, bool $platform): void
    {
        $this->post('/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole($role));

        $dashboard = $this->get('/dashboard')->assertOk()->assertSee($role);

        if ($platform) {
            $this->assertNull($user->tenant_id);
            $this->assertTrue($user->isSuperAdmin());
            $dashboard->assertSee('Platform');
        } else {
            $this->assertNotNull($user->tenant_id);
            $this->assertFalse($user->isSuperAdmin());
            $dashboard->assertSee('Demo Tenant');
        }
    }

    public function test_tenant_demo_user_cannot_see_another_tenants_user(): void
    {
        $other = app(TenantContext::class)->bypass(function (): User {
            $tenant = Tenant::query()->create([
                'name' => 'Other Tenant',
                'slug' => 'other-tenant',
                'email' => 'other-tenant@example.com',
                'status' => TenantStatus::Active,
            ]);

            return User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Other Admin',
                'email' => 'other-admin@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);
        });

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get('/users')
            ->assertOk()
            ->assertSee('manager@example.com')
            ->assertDontSee('other-admin@example.com');

        $this->put('/users/'.$other->id.'/role', [
            'role' => RoleName::Supervisor->value,
        ])->assertNotFound();

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get('/users')
            ->assertOk()
            ->assertSee('other-admin@example.com')
            ->assertSee('admin@example.com');
    }
}
