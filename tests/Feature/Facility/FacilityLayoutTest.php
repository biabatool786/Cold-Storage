<?php

namespace Tests\Feature\Facility;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\ChamberSetPointChange;
use App\Models\Csms\Slot;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private Branch $other;

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
            $otherTenant = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'email' => 'b@example.com', 'status' => TenantStatus::Active]);
            $this->branchA = Branch::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active]);
            $this->other = Branch::query()->create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'OTHER', 'status' => BranchStatus::Active]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com');
            foreach ([$this->managerA, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
        });
    }

    public function test_facility_layout_follows_the_matrix(): void
    {
        $this->actingAs($this->adminA)->post('/branches', ['name' => 'West', 'code' => 'WEST'])->assertForbidden();
        $this->actingAs($this->supervisorA)->put('/branches/'.$this->branchA->id, [
            'name' => 'North Hall', 'status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertSame('North Hall', $this->branchA->fresh()->name);
        $this->assertTrue(AuditLog::query()->where('action', 'branch.updated')->exists());

        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/chambers', ['name' => 'One', 'code' => 'C1'])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/chambers', ['name' => 'One', 'code' => 'C1'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', [
            'name' => 'One', 'code' => 'C1', 'capacity' => 1000, 'capacity_unit' => 'kg',
        ])->assertRedirect();
        $chamber = Chamber::query()->where('code', 'C1')->firstOrFail();
        $this->assertSame($this->tenantA->id, $chamber->tenant_id);

        $this->actingAs($this->managerA)->put('/chambers/'.$chamber->id, ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($this->supervisorA)->put('/chambers/'.$chamber->id, ['name' => 'Room One', 'capacity' => 1200])->assertRedirect();

        $this->actingAs($this->managerA)->post('/chambers/'.$chamber->id.'/set-points', ['temperature' => -18, 'humidity' => 80])->assertForbidden();
        $this->actingAs($this->adminA)->post('/chambers/'.$chamber->id.'/set-points', ['temperature' => -18, 'humidity' => 80])->assertRedirect();
        $change = ChamberSetPointChange::query()->firstOrFail();
        $change->update(['created_by' => $this->managerA->id]);
        $this->actingAs($this->managerA)->post('/set-points/'.$change->id, ['approve' => 1])->assertForbidden();
        $change->update(['created_by' => $this->adminA->id]);
        $this->actingAs($this->managerA)->post('/set-points/'.$change->id, ['approve' => 1])->assertRedirect();
        $this->assertEquals(-18, (float) $chamber->fresh()->set_point);
        $this->assertTrue(AuditLog::query()->where('action', 'chamber.set_point_approved')->exists());

        $this->actingAs($this->supervisorA)->post('/chambers/'.$chamber->id.'/status', ['status' => 'maintenance'])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/chambers/'.$chamber->id.'/status', ['status' => 'defrost'])->assertRedirect();
        $this->assertSame('defrost', $chamber->fresh()->status);

        $this->actingAs($this->managerA)->post('/chambers/'.$chamber->id.'/racks', ['name' => 'Rack A', 'code' => 'R1'])->assertRedirect();
        $rack = \App\Models\Csms\Rack::query()->firstOrFail();
        $this->actingAs($this->adminA)->post('/racks/'.$rack->id.'/levels', ['name' => 'Level 1', 'code' => 'L1'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/racks/'.$rack->id.'/levels', ['name' => 'Level 1', 'code' => 'L1'])->assertRedirect();
        $level = \App\Models\Csms\Level::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->put('/levels/'.$level->id, ['name' => 'Lower'])->assertRedirect();
        $this->actingAs($this->managerA)->post('/levels/'.$level->id.'/slots', ['code' => 'S1'])->assertRedirect();
        $slot = Slot::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/slots/'.$slot->id.'/block', ['blocked' => 1, 'reason' => 'Repair'])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/slots/'.$slot->id.'/block', ['blocked' => 1, 'reason' => 'Repair'])->assertRedirect();
        $this->assertSame('blocked', $slot->fresh()->status);
        $this->assertNotNull($slot->fresh()->blocked_at);

        $this->actingAs($this->adminA)->delete('/chambers/'.$chamber->id, ['reason' => 'Gone'])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/chambers')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/occupancy')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/chambers')->assertOk()->assertSee('1200')->assertDontSee('Temperature');
        $this->actingAs($this->accountantA)->get('/occupancy')->assertOk()->assertSee('blocked');
        $this->actingAs($this->adminA)->get('/chambers/'.$this->chamberOnOtherBranch())->assertNotFound();

        $this->actingAs($this->superAdmin)->delete('/slots/'.$slot->id, ['reason' => 'Removed'])->assertRedirect();
        $this->assertSoftDeleted('slots', ['id' => $slot->id]);
    }

    private function chamberOnOtherBranch(): int
    {
        return app(TenantContext::class)->bypass(fn () => Chamber::query()->create([
            'tenant_id' => $this->other->tenant_id,
            'branch_id' => $this->other->id,
            'name' => 'Foreign',
            'code' => 'FX',
            'status' => 'active',
        ])->id);
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
