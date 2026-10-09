<?php

namespace Tests\Feature\Dispatch;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GatePass;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\PutAwayTask;
use App\Models\Csms\Slot;
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

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private Branch $branchB;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

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
            $this->branchA = $this->branch($this->tenantA, 'North', 'NORTH');
            $this->branchB = $this->branch($this->tenantA, 'South', 'SOUTH');
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com', null);
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com', $this->branchA);
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com', $this->branchA);
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com', $this->branchA);
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com', $this->branchA);
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com', $this->branchA);
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com', $this->branchA);
        });
    }

    public function test_dispatch_workflow_permissions_and_stock_movements(): void
    {
        $lot = $this->seedStoredLot(100);

        $this->actingAs($this->adminA)->post('/delivery-orders', [
            'storage_lot_id' => $lot->id, 'quantity' => 40,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/delivery-orders', [
            'storage_lot_id' => $lot->id, 'quantity' => 40,
        ])->assertForbidden();

        $this->actingAs($this->supervisorA)->post('/delivery-orders', [
            'storage_lot_id' => $lot->id, 'quantity' => 40, 'pick_mode' => 'fifo', 'submit' => 1,
        ])->assertRedirect();
        $order = DeliveryOrder::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertTrue($order->stock_ok);

        $this->actingAs($this->gatekeeperA)->get('/delivery-orders/'.$order->id)->assertNotFound();
        $this->actingAs($this->supervisorA)->get('/delivery-orders/'.$order->id)->assertOk()->assertSee('Clear');
        $this->actingAs($this->accountantA)->get('/delivery-orders/'.$order->id)->assertOk();

        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/approve')->assertForbidden();
        $this->actingAs($this->accountantA)->post('/delivery-orders/'.$order->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/delivery-orders/'.$order->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $order->fresh()->status);

        $this->actingAs($this->gatekeeperA)->get('/delivery-orders/'.$order->id)->assertOk();

        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/pick-list', [
            'override' => 1,
        ])->assertRedirect()->assertSessionHasErrors('reason');
        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/pick-list', [
            'override' => 1, 'reason' => 'Customer requested newer lot',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('picking', $order->status);
        $line = $order->pickLines()->firstOrFail();

        $this->actingAs($this->supervisorA)->post('/delivery-pick-lines/'.$line->id.'/pick', [
            'picked_qty' => (float) $line->suggested_qty,
            'slot_id' => $line->slot_id,
        ])->assertRedirect();
        $this->assertSame('picked', $order->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/temperature', [
            'temperature' => 3.5,
        ])->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/loaders', [
            'loader_count' => 2, 'loader_notes' => 'Team A',
        ])->assertRedirect();

        $this->actingAs($this->gatekeeperA)->post('/delivery-orders/'.$order->id.'/verify-loading', [
            'final_count' => 40,
        ])->assertRedirect();
        $this->assertTrue($order->fresh()->loading_verified);

        $this->actingAs($this->gatekeeperA)->post('/delivery-orders/'.$order->id.'/pass')->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/pass')->assertRedirect();
        $pass = GatePass::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('issued', $pass->status);

        $this->actingAs($this->accountantA)->get('/gate-passes/'.$pass->id.'/print')->assertOk();
        $this->actingAs($this->gatekeeperA)->post('/gate-passes/'.$pass->id.'/verify')->assertRedirect();
        $this->assertSame('dispatched', $order->fresh()->status);
        $this->assertEquals(60, (float) $lot->fresh()->quantity);
        $this->assertEquals(40, (float) $lot->fresh()->dispatched_qty);
        $this->assertTrue(AuditLog::query()->where('action', 'gate.released')->exists());

        $this->actingAs($this->supervisorA)->post('/dispatch/returns', [
            'delivery_order_id' => $order->id, 'quantity' => 5, 'reason' => 'Damaged on road', 'condition' => 'wet',
        ])->assertRedirect();
        $return = \App\Models\Csms\DispatchReturn::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame('pending', $return->status);
        $this->actingAs($this->managerA)->post('/dispatch/returns/'.$return->id.'/approve')->assertRedirect();
        $this->assertEquals(65, (float) $lot->fresh()->quantity);

        $this->actingAs($this->supervisorA)->post('/dispatch/transfer-outs', [
            'storage_lot_id' => $lot->id, 'to_branch_id' => $this->branchB->id, 'quantity' => 10, 'reason' => 'Rebalance',
        ])->assertRedirect();
        $xfer = \App\Models\Csms\BranchTransferOut::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/dispatch/transfer-outs/'.$xfer->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/dispatch/transfer-outs/'.$xfer->id.'/approve')->assertRedirect();
        $this->assertEquals(55, (float) $lot->fresh()->quantity);

        $this->actingAs($this->managerA)->delete('/delivery-orders/'.$order->id, ['reason' => 'Nope'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/delivery-orders/'.$order->id, ['reason' => 'Cleanup'])->assertRedirect();
        $this->assertSoftDeleted('delivery_orders', ['id' => $order->id]);
    }

    private function seedStoredLot(float $qty): StorageLot
    {
        $this->actingAs($this->managerA)->post('/commodities', [
            'name' => 'Onion', 'variety' => 'Red', 'grade' => 'A',
        ])->assertRedirect();
        $commodity = Commodity::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Buyer Co', 'credit_limit' => 50000,
        ])->assertRedirect();
        $customer = Customer::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', [
            'name' => 'C1', 'code' => 'C1',
        ])->assertRedirect();
        $chamberId = \App\Models\Csms\Chamber::query()->withoutGlobalScopes()->value('id');
        $this->actingAs($this->managerA)->post('/chambers/'.$chamberId.'/racks', ['name' => 'R', 'code' => 'R1'])->assertRedirect();
        $rackId = \App\Models\Csms\Rack::query()->withoutGlobalScopes()->value('id');
        $this->actingAs($this->managerA)->post('/racks/'.$rackId.'/levels', ['name' => 'L', 'code' => 'L1'])->assertRedirect();
        $levelId = \App\Models\Csms\Level::query()->withoutGlobalScopes()->value('id');
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S1'])->assertRedirect();
        $slot = Slot::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => $qty,
        ])->assertRedirect();
        $booking = \App\Models\Csms\Booking::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->manager2)->post('/bookings/'.$booking->id.'/approve')->assertRedirect();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'OUT-1', 'purpose' => 'inbound', 'customer_id' => $customer->id, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $entry = GateEntry::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => $qty, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $receipt = GoodsReceipt::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->managerA)->post('/grns/'.$receipt->id.'/approve', [
            'chamber_id' => $chamberId, 'suggested_slot_id' => $slot->id,
        ])->assertRedirect();
        $lot = StorageLot::query()->withoutGlobalScopes()->firstOrFail();
        $task = PutAwayTask::query()->withoutGlobalScopes()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/put-aways/'.$task->id.'/execute', ['slot_id' => $slot->id])->assertRedirect();

        return $lot->fresh();
    }

    private function branch(Tenant $tenant, string $name, string $code): Branch
    {
        return Branch::query()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'code' => $code, 'status' => BranchStatus::Active,
        ]);
    }

    private function user(?Tenant $tenant, RoleName $role, string $email, ?Branch $branch): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id, 'name' => $email, 'email' => $email, 'password' => 'password', 'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());
        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        return $user;
    }
}
