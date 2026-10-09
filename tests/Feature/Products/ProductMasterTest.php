<?php

namespace Tests\Feature\Products;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Csms\Commodity;
use App\Models\Csms\CommodityGrade;
use App\Models\Csms\CommodityShrinkageChange;
use App\Models\Csms\CommodityVariety;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMasterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'email' => 'a@example.com', 'status' => TenantStatus::Active]);
            $this->tenantB = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'email' => 'b@example.com', 'status' => TenantStatus::Active]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com');
        });
    }

    public function test_product_master_follows_the_matrix(): void
    {
        $this->actingAs($this->adminA)->post('/commodities', ['name' => 'Potato'])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/commodities', ['name' => 'Potato'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/commodities', [
            'name' => 'Potato', 'code' => 'POT', 'variety' => 'Lady Rosetta', 'grade' => 'A', 'tenant_id' => $this->tenantB->id,
        ])->assertRedirect();
        $commodity = Commodity::query()->where('name', 'Potato')->firstOrFail();
        $this->assertSame($this->tenantA->id, $commodity->tenant_id);
        $variety = CommodityVariety::query()->where('commodity_id', $commodity->id)->firstOrFail();
        $this->assertTrue(CommodityGrade::query()->where('commodity_variety_id', $variety->id)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'product.created')->exists());

        $this->actingAs($this->supervisorA)->put('/commodities/'.$commodity->id, ['name' => 'Potato White', 'code' => 'POT'])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->put('/varieties/'.$variety->id, ['name' => 'Rosetta'])->assertRedirect();
        $this->actingAs($this->supervisorA)->put('/commodities/'.$commodity->id.'/storage', ['shelf_life_days' => 10])->assertForbidden();
        $this->actingAs($this->managerA)->put('/commodities/'.$commodity->id.'/storage', [
            'storage_temperature' => -2, 'storage_humidity' => 90, 'shelf_life_days' => 30,
        ])->assertRedirect();
        $this->assertSame(30, $commodity->fresh()->shelf_life_days);

        $this->actingAs($this->gatekeeperA)->put('/commodities/'.$commodity->id.'/units', ['unit' => 'bag', 'factor_to_kg' => 50])->assertForbidden();
        $this->actingAs($this->accountantA)->put('/commodities/'.$commodity->id.'/units', ['unit' => 'bag', 'factor_to_kg' => 50])->assertForbidden();
        $this->actingAs($this->adminA)->put('/commodities/'.$commodity->id.'/units', ['unit' => 'bag', 'factor_to_kg' => 50])->assertRedirect();
        $this->actingAs($this->managerA)->get('/commodities/'.$commodity->id)->assertOk()->assertDontSee('1 bag');
        $this->actingAs($this->accountantA)->get('/commodities/'.$commodity->id)->assertOk()->assertSee('1 bag')->assertDontSee('Save unit');

        $this->actingAs($this->managerA)->post('/commodities', ['name' => 'Onion', 'code' => 'ONI'])->assertRedirect();
        $onion = Commodity::query()->where('code', 'ONI')->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/commodities/'.$commodity->id.'/incompatible', ['incompatible_commodity_id' => $onion->id])->assertForbidden();
        $this->actingAs($this->managerA)->post('/commodities/'.$commodity->id.'/incompatible', ['incompatible_commodity_id' => $onion->id])->assertRedirect();

        $this->actingAs($this->managerA)->post('/commodities/'.$commodity->id.'/shrinkage', ['percent' => 2.5])->assertForbidden();
        $this->actingAs($this->adminA)->post('/commodities/'.$commodity->id.'/shrinkage', ['percent' => 2.5])->assertRedirect();
        $change = CommodityShrinkageChange::query()->firstOrFail();
        $change->update(['created_by' => $this->managerA->id]);
        $this->actingAs($this->managerA)->post('/shrinkage/'.$change->id, ['approve' => 1])->assertForbidden();
        $change->update(['created_by' => $this->adminA->id]);
        $this->actingAs($this->managerA)->post('/shrinkage/'.$change->id, ['approve' => 1])->assertRedirect();
        $this->assertEquals(2.5, (float) $commodity->fresh()->shrinkage_percent);
        $this->assertTrue(AuditLog::query()->where('action', 'product.shrinkage_approved')->exists());

        $this->actingAs($this->managerA)->post('/commodities/'.$commodity->id.'/deactivate')->assertForbidden();
        $this->actingAs($this->adminA)->post('/commodities/'.$commodity->id.'/deactivate')->assertRedirect();
        $this->assertSame('inactive', $commodity->fresh()->status);
        $this->actingAs($this->adminA)->delete('/commodities/'.$commodity->id, ['reason' => 'Retired'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/commodities/'.$commodity->id, ['reason' => 'Retired'])->assertRedirect();
        $this->assertSoftDeleted('commodities', ['id' => $commodity->id]);

        $foreign = app(TenantContext::class)->bypass(fn () => Commodity::query()->create([
            'tenant_id' => $this->tenantB->id, 'name' => 'Foreign', 'status' => 'active',
        ]));
        $this->actingAs($this->adminA)->get('/commodities/'.$foreign->id)->assertNotFound();
    }

    private function user(?Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id, 'name' => $email, 'email' => $email, 'password' => 'password', 'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}
