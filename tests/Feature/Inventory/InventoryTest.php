<?php

namespace Tests\Feature\Inventory;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\PutAwayTask;
use App\Models\Csms\Slot;
use App\Models\Csms\StockAdjustment;
use App\Models\Csms\StockHold;
use App\Models\Csms\StockLedgerEntry;
use App\Models\Csms\StockTransfer;
use App\Models\Csms\StorageLot;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchOther;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private User $managerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A', 'slug' => 'tenant-a', 'email' => 'a@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->tenantB = Tenant::query()->create([
                'name' => 'Tenant B', 'slug' => 'tenant-b', 'email' => 'b@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = $this->branch($this->tenantA, 'North', 'NORTH');
            $this->branchOther = $this->branch($this->tenantB, 'Other', 'OTHER');
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com', null);
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com', $this->branchA);
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com', $this->branchA);
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com', $this->branchA);
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com', $this->branchA);
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com', $this->branchA);
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com', $this->branchA);
            $this->managerB = $this->user($this->tenantB, RoleName::Manager, 'manager-b@example.com', $this->branchOther);
        });
    }

    public function test_inventory_matrix_stock_ledger_transfers_holds_and_adjustments(): void
    {
        [$lot, $slot1, $slot2, $chamberId] = $this->seedStoredLot();

        $this->actingAs($this->gatekeeperA)->get('/inventory')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/lots')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/inventory/ledger')->assertOk();
        $this->actingAs($this->accountantA)->get('/inventory')->assertOk()->assertSee($lot->lot_number);
        $this->actingAs($this->adminA)->get('/inventory/suggestions')->assertOk();

        $this->assertTrue(StockLedgerEntry::query()->where('storage_lot_id', $lot->id)->where('type', 'receipt')->exists());

        $this->actingAs($this->supervisorA)->get('/inventory/suggestions?mode=fifo')->assertOk()->assertSee($lot->lot_number);
        $this->actingAs($this->accountantA)->get('/inventory/suggestions')->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/inventory/transfers', [
            'storage_lot_id' => $lot->id, 'to_slot_id' => $slot2->id, 'quantity' => 10,
        ])->assertForbidden();

        $this->actingAs($this->supervisorA)->post('/inventory/transfers', [
            'storage_lot_id' => $lot->id, 'to_slot_id' => $slot2->id, 'quantity' => 20, 'reason' => 'Rebalance',
        ])->assertRedirect();
        $transfer = StockTransfer::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/inventory/transfers/'.$transfer->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/inventory/transfers/'.$transfer->id.'/approve')->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/inventory/transfers/'.$transfer->id.'/execute', [
            'from_slot_id' => $slot1->id, 'to_slot_id' => $slot2->id,
        ])->assertRedirect();
        $this->assertSame($slot2->id, $lot->fresh()->slot_id);
        $this->assertSame('available', $slot1->fresh()->status);
        $this->assertSame('occupied', $slot2->fresh()->status);
        $this->assertTrue(StockLedgerEntry::query()->where('type', 'transfer')->exists());

        $this->actingAs($this->supervisorA)->post('/inventory/holds', [
            'storage_lot_id' => $lot->id, 'kind' => 'quarantine', 'quantity' => 5, 'reason' => 'Suspect quality',
        ])->assertRedirect();
        $hold = StockHold::query()->firstOrFail();
        $this->assertSame('requested', $hold->status);
        $this->actingAs($this->managerA)->post('/inventory/holds/'.$hold->id.'/activate')->assertRedirect();
        $this->assertEquals(5, (float) $lot->fresh()->quarantined_qty);
        $this->actingAs($this->supervisorA)->post('/inventory/holds/'.$hold->id.'/release', [
            'reason' => 'Cleared',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/inventory/holds/'.$hold->id.'/release', [
            'reason' => 'Lab cleared',
        ])->assertRedirect();
        $this->assertEquals(0, (float) $lot->fresh()->quarantined_qty);

        $this->actingAs($this->supervisorA)->post('/inventory/services', [
            'storage_lot_id' => $lot->id, 'service_type' => 'sorting', 'quantity' => 10, 'notes' => 'Sort A',
        ])->assertRedirect();
        $service = \App\Models\Csms\ValueAddedService::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/inventory/services/'.$service->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/inventory/services/'.$service->id.'/approve')->assertRedirect();

        $before = (float) $lot->fresh()->quantity;
        $this->actingAs($this->supervisorA)->post('/inventory/shrinkage', [
            'storage_lot_id' => $lot->id, 'quantity' => 2, 'reason' => 'Moisture loss', 'recorded_on' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertEquals($before - 2, (float) $lot->fresh()->quantity);
        $this->assertTrue(StockLedgerEntry::query()->where('type', 'shrinkage')->exists());

        $this->actingAs($this->supervisorA)->post('/inventory/counts', [
            'kind' => 'physical', 'chamber_id' => $chamberId,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/inventory/counts', [
            'kind' => 'cycle', 'chamber_id' => $chamberId,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $count = \App\Models\Csms\StockCount::query()->withoutGlobalScopes()->firstOrFail();
        $line = $count->lines()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/inventory/count-lines/'.$line->id, [
            'counted_qty' => ((float) $line->system_qty) - 1,
        ])->assertRedirect();
        $this->assertEquals(-1, (float) $line->fresh()->variance);
        $this->actingAs($this->accountantA)->get('/inventory/variances')->assertOk();

        $this->actingAs($this->supervisorA)->post('/inventory/adjustments', [
            'storage_lot_id' => $lot->id, 'kind' => 'adjustment',
            'quantity_after' => (float) $lot->fresh()->quantity - 1, 'reason' => 'Count variance',
        ])->assertRedirect();
        $adj = StockAdjustment::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/inventory/adjustments/'.$adj->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/inventory/adjustments/'.$adj->id.'/approve')->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'inventory.adjustment_approved')->exists());

        $lot->update(['quantity' => 2500]);
        $this->actingAs($this->managerA)->post('/inventory/adjustments', [
            'storage_lot_id' => $lot->id, 'kind' => 'write_off',
            'quantity_after' => 100, 'reason' => 'Large write-off',
        ])->assertRedirect();
        $writeOff = StockAdjustment::query()->where('kind', 'write_off')->latest('id')->firstOrFail();
        $this->assertEquals(2400, abs((float) $writeOff->delta));
        $this->actingAs($this->manager2)->post('/inventory/adjustments/'.$writeOff->id.'/approve')->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/inventory/adjustments/'.$writeOff->id.'/approve')->assertRedirect();

        $this->actingAs($this->supervisorA)->get('/inventory/lots/'.$lot->id.'/labels')->assertOk();
        $this->actingAs($this->adminA)->get('/inventory/lots/'.$lot->id.'/labels')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/inventory/export')->assertStatus(422);
        $this->actingAs($this->supervisorA)->get('/inventory/export?chamber_id='.$chamberId.'&download=1')->assertOk();
        $this->actingAs($this->accountantA)->get('/inventory/export?download=1')->assertOk();
        $this->actingAs($this->managerB)->get('/inventory/lots/'.$lot->id)->assertNotFound();
        $this->actingAs($this->adminA)->get('/inventory/aging')->assertOk();
    }

    /**
     * @return array{0: StorageLot, 1: Slot, 2: Slot, 3: int}
     */
    private function seedStoredLot(): array
    {
        $this->actingAs($this->managerA)->post('/commodities', [
            'name' => 'Mango', 'variety' => 'Sindhri', 'grade' => 'A',
        ])->assertRedirect();
        $commodity = Commodity::query()->firstOrFail();
        $this->actingAs($this->managerA)->put('/commodities/'.$commodity->id.'/storage', [
            'shelf_life_days' => 30,
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Orchard Co',
        ])->assertRedirect();
        $customer = Customer::query()->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', [
            'name' => 'Chamber 1', 'code' => 'C1',
        ])->assertRedirect();
        $chamberId = (int) \App\Models\Csms\Chamber::query()->value('id');
        $this->actingAs($this->managerA)->post('/chambers/'.$chamberId.'/racks', ['name' => 'R', 'code' => 'R1'])->assertRedirect();
        $rackId = \App\Models\Csms\Rack::query()->value('id');
        $this->actingAs($this->managerA)->post('/racks/'.$rackId.'/levels', ['name' => 'L', 'code' => 'L1'])->assertRedirect();
        $levelId = \App\Models\Csms\Level::query()->value('id');
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S1'])->assertRedirect();
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S2'])->assertRedirect();
        $slot1 = Slot::query()->where('code', 'S1')->firstOrFail();
        $slot2 = Slot::query()->where('code', 'S2')->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 80,
        ])->assertRedirect();
        $booking = \App\Models\Csms\Booking::query()->firstOrFail();
        $this->actingAs($this->manager2)->post('/bookings/'.$booking->id.'/approve')->assertRedirect();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'INV-1', 'purpose' => 'inbound', 'customer_id' => $customer->id, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $entry = GateEntry::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 80, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $receipt = GoodsReceipt::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/grns/'.$receipt->id.'/approve', [
            'chamber_id' => $chamberId, 'suggested_slot_id' => $slot1->id,
        ])->assertRedirect();
        $lot = StorageLot::query()->firstOrFail();
        $task = PutAwayTask::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slot1->id,
        ])->assertRedirect();

        return [$lot->fresh(), $slot1->fresh(), $slot2->fresh(), $chamberId];
    }

    private function branch(Tenant $tenant, string $name, string $code): Branch
    {
        return Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $code,
            'status' => BranchStatus::Active,
        ]);
    }

    private function user(?Tenant $tenant, RoleName $role, string $email, ?Branch $branch): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());
        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        return $user;
    }
}
