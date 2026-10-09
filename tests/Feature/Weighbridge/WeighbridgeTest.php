<?php

namespace Tests\Feature\Weighbridge;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\Weighment;
use App\Models\Csms\WeighmentEditRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighbridgeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private GateEntry $entry;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'email' => 'a@example.com', 'status' => TenantStatus::Active]);
            $this->branchA = Branch::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com');
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com');
            foreach ([$this->managerA, $this->manager2, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
            $commodity = Commodity::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Potato', 'status' => 'active']);
            $customer = Customer::query()->create(['tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id, 'name' => 'Ali']);
            $this->booking = Booking::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id, 'customer_id' => $customer->id,
                'commodity_id' => $commodity->id, 'quantity' => 100, 'status' => 'approved',
            ]);
            $this->entry = GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id, 'customer_id' => $customer->id,
                'booking_id' => $this->booking->id, 'vehicle_no' => 'LES-100', 'purpose' => 'inbound', 'status' => 'open',
                'created_by' => $this->gatekeeperA->id,
            ]);
        });
    }

    public function test_weighbridge_follows_the_matrix(): void
    {
        $this->actingAs($this->managerA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'gross', 'weight' => 1500,
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'gross', 'weight' => 1500,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'gross', 'weight' => 1500, 'manual' => 1,
        ])->assertSessionHasErrors('reason');
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'gross', 'weight' => 1500,
        ])->assertRedirect();
        $ticket = Weighment::query()->firstOrFail();
        $this->assertEquals(1500, (float) $ticket->gross_weight);
        $this->assertNull($ticket->net_weight);
        $this->assertTrue(AuditLog::query()->where('action', 'weighment.recorded')->exists());

        $this->actingAs($this->gatekeeperA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'tare', 'weight' => 500,
        ])->assertRedirect();
        $this->assertEquals(1000, (float) $ticket->fresh()->net_weight);

        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/weighments/tolerance', ['limit_kg' => 50])->assertRedirect();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/weighments/tolerance', ['limit_kg' => 10])->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'tare', 'weight' => 200,
        ])->assertRedirect();
        $this->assertSame('alert', $ticket->fresh()->tolerance_status);
        $this->actingAs($this->accountantA)->post('/weighments/'.$ticket->id.'/acknowledge')->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/weighments/'.$ticket->id.'/acknowledge', ['comment' => 'Checked'])->assertRedirect();
        $this->assertSame('acknowledged', $ticket->fresh()->tolerance_status);

        $this->actingAs($this->gatekeeperA)->post('/gates/'.$this->entry->id.'/weigh', [
            'kind' => 'gross', 'weight' => 1400, 'manual' => 1, 'reason' => 'Indicator offline',
        ])->assertRedirect();
        $manual = Weighment::query()->where('is_manual', true)->latest('id')->first()
            ?? $ticket->fresh();
        if (! $manual->is_manual) {
            $manual = Weighment::query()->latest('id')->firstOrFail();
        }
        $this->actingAs($this->gatekeeperA)->post('/weighments/'.$manual->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/weighments/'.$ticket->id.'/approve')->assertRedirect();

        $this->actingAs($this->managerA)->put('/weighments/'.$ticket->id, [
            'gross_weight' => 1510, 'tare_weight' => 500, 'reason' => 'Corrected',
        ])->assertRedirect();
        $edit = WeighmentEditRequest::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/weighment-edits/'.$edit->id, ['approve' => 1])->assertForbidden();
        $this->actingAs($this->manager2)->post('/weighment-edits/'.$edit->id, ['approve' => 1])->assertRedirect();
        $this->assertEquals(1510, (float) $ticket->fresh()->gross_weight);

        $this->actingAs($this->adminA)->delete('/weighments/'.$ticket->id, ['reason' => 'Mistake'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/weighments/'.$ticket->id, ['reason' => 'Mistake'])->assertRedirect();
        $this->assertSoftDeleted('weighments', ['id' => $ticket->id]);

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/weighments/calibration', [
            'calibrated_on' => '2026-10-01',
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/weighments/calibration', [
            'calibrated_on' => '2026-10-01', 'certificate_ref' => 'CERT-1',
        ])->assertRedirect();
        $this->actingAs($this->accountantA)->get('/weighments')->assertOk();
        $this->actingAs($this->accountantA)->get('/weighments/calibration')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/weighments')->assertOk();

        $other = Weighment::query()->create([
            'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id, 'gate_entry_id' => $this->entry->id,
            'number' => 'W-OTHER', 'kind' => 'gross', 'weight' => 1, 'gross_weight' => 1, 'created_by' => $this->managerA->id,
        ]);
        $this->actingAs($this->gatekeeperA)->get('/weighments')->assertOk()->assertDontSee('W-OTHER');
        $this->actingAs($this->managerA)->get('/weighments')->assertOk()->assertSee('W-OTHER');
        $this->actingAs($this->managerA)->get('/weighments/'.$other->id.'/print')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'weighment.printed')->exists());
        $this->actingAs($this->adminA)->get('/weighments/'.$other->id.'/print')->assertForbidden();
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
