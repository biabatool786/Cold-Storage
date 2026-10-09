<?php

namespace Tests\Feature\Gate;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GatePass;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\StorageLot;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GateManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private Branch $branchB;

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
            $other = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'email' => 'b@example.com', 'status' => TenantStatus::Active]);
            $this->branchA = Branch::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active]);
            $this->branchB = Branch::query()->create(['tenant_id' => $other->id, 'name' => 'Other', 'code' => 'OTHER', 'status' => BranchStatus::Active]);
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

    public function test_gate_management_follows_the_matrix(): void
    {
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'LES-1', 'purpose' => 'inbound',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/gates')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchB->id.'/gates', [
            'vehicle_no' => 'LES-1', 'purpose' => 'inbound',
        ])->assertNotFound();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'LES-1', 'driver_name' => 'Ali', 'purpose' => 'inbound', 'seal_number' => 'SEAL9', 'vehicle_condition' => 'Good',
        ])->assertRedirect();
        $entry = GateEntry::query()->where('vehicle_no', 'LES-1')->firstOrFail();
        $this->assertTrue(AuditLog::query()->where('action', 'gate.entered')->exists());
        $this->actingAs($this->gatekeeperA)->get('/gates')->assertOk()->assertSee('LES-1');
        $this->actingAs($this->managerA)->get('/gates/'.$entry->id)->assertOk()->assertDontSee('SEAL9');
        $this->actingAs($this->supervisorA)->get('/gates/'.$entry->id)->assertOk()->assertSee('SEAL9');

        $this->actingAs($this->gatekeeperA)->put('/gates/'.$entry->id, [
            'vehicle_no' => 'LES-1A', 'purpose' => 'inbound', 'seal_number' => 'SEAL9',
        ])->assertRedirect();
        GateEntry::query()->whereKey($entry->id)->update(['created_at' => now()->subDay()]);
        $this->actingAs($this->gatekeeperA)->put('/gates/'.$entry->id, [
            'vehicle_no' => 'LES-1B', 'purpose' => 'inbound',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/gates/history')->assertOk()->assertSee('LES-1A');

        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/exit')->assertRedirect();
        $this->assertSame('closed', $entry->fresh()->status);
        $this->actingAs($this->gatekeeperA)->put('/gates/'.$entry->id, [
            'vehicle_no' => 'LES-1C', 'purpose' => 'inbound',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->put('/gates/'.$entry->id, [
            'vehicle_no' => 'LES-1C', 'purpose' => 'inbound',
        ])->assertSessionHasErrors('reason');
        $this->actingAs($this->managerA)->put('/gates/'.$entry->id, [
            'vehicle_no' => 'LES-1C', 'purpose' => 'inbound', 'reason' => 'Corrected plate',
        ])->assertRedirect();

        $old = GateEntry::query()->where('vehicle_no', 'LES-1C')->firstOrFail();
        GateEntry::query()->whereKey($old->id)->update(['created_at' => now()->subDays(8)]);
        $this->actingAs($this->supervisorA)->get('/gates/history')->assertOk()->assertDontSee('LES-1C');
        $this->actingAs($this->adminA)->get('/gates/history')->assertOk()->assertSee('LES-1C');
        $this->actingAs($this->supervisorA)->get('/gates/history/print')->assertForbidden();
        $this->actingAs($this->adminA)->get('/gates/history/print')->assertOk();
        $this->actingAs($this->managerA)->get('/gates/'.$old->id.'/token')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/gates/'.$old->id.'/token')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'gate.token_printed')->exists());

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'OUT-1', 'purpose' => 'outbound',
        ])->assertRedirect();
        $outbound = GateEntry::query()->where('vehicle_no', 'OUT-1')->firstOrFail();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/exit')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/override', ['reason' => 'No pass'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/gates/'.$outbound->id.'/override', ['reason' => 'Customer waiting'])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'gate.override_approved')->exists());
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/exit')->assertRedirect();

        $this->actingAs($this->managerA)->post('/gates/'.$outbound->id.'/photo', [
            'photo' => UploadedFile::fake()->image('cab.jpg'),
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/photo', [
            'photo' => UploadedFile::fake()->image('cab.jpg'),
        ])->assertRedirect();

        $pass = $this->passFor($outbound);
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/verify', ['code' => 'MISSING'])->assertStatus(422);
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$outbound->id.'/verify', ['code' => $pass->code])->assertRedirect();
        $this->assertNotNull($outbound->fresh()->pass_verified_at);

        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/visitors', ['name' => 'Contractor'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/visitors', ['name' => 'Contractor'])->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/blocks', [
            'vehicle_no' => 'BLK-1', 'reason' => 'Unpaid',
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/blocks', [
            'vehicle_no' => 'BLK-1', 'reason' => 'Unpaid',
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'BLK-1', 'purpose' => 'inbound',
        ])->assertRedirect();
        $blocked = GateEntry::query()->where('vehicle_no', 'BLK-1')->firstOrFail();
        $this->actingAs($this->gatekeeperA)->get('/gates/'.$blocked->id)->assertOk()->assertSee('Blocked vehicle or driver.');
        $this->actingAs($this->supervisorA)->get('/gates/blocked')->assertOk()->assertSee('BLK-1');

        $this->actingAs($this->managerA)->post('/commodities', ['name' => 'Potato', 'code' => 'POT'])->assertRedirect();
        $commodity = Commodity::query()->where('code', 'POT')->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', ['name' => 'Ali Traders'])->assertRedirect();
        $customer = Customer::query()->where('name', 'Ali Traders')->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 5,
            'vehicle_no' => 'EXP-9', 'starts_on' => today()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->get('/gates/expected')->assertOk()->assertSee('EXP-9');
        $this->assertTrue(Booking::query()->where('vehicle_no', 'EXP-9')->exists());

        $this->actingAs($this->managerA)->delete('/gates/'.$blocked->id, ['reason' => 'Mistake'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/gates/'.$blocked->id, ['reason' => 'Mistake'])->assertRedirect();
        $this->assertSoftDeleted('gate_entries', ['id' => $blocked->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'gate.deleted')->exists());
    }

    private function passFor(GateEntry $entry): GatePass
    {
        $customer = Customer::query()->create(['tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'name' => 'Pass Customer']);
        $commodity = Commodity::query()->create(['tenant_id' => $entry->tenant_id, 'name' => 'Pass Goods', 'status' => 'active']);
        $chamber = \App\Models\Csms\Chamber::query()->create(['tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'name' => 'Room', 'code' => 'R1', 'status' => 'active']);
        $receipt = GoodsReceipt::query()->create([
            'tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'gate_entry_id' => $entry->id,
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 1, 'status' => 'approved',
        ]);
        $lot = StorageLot::query()->create([
            'tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'goods_receipt_id' => $receipt->id,
            'chamber_id' => $chamber->id, 'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 1,
        ]);
        $order = DeliveryOrder::query()->create([
            'tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'storage_lot_id' => $lot->id,
            'customer_id' => $customer->id, 'quantity' => 1, 'status' => 'approved',
        ]);

        return GatePass::query()->create([
            'tenant_id' => $entry->tenant_id, 'branch_id' => $entry->branch_id, 'delivery_order_id' => $order->id,
            'status' => 'issued', 'code' => 'PASS1',
        ]);
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
