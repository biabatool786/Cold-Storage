<?php

namespace Tests\Feature\Operations;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\Branch;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\SensorReading;
use App\Models\Csms\StorageInvoice;
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

class GoodsFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchB;

    private Branch $branchOther;

    private User $adminA;

    private User $managerA;

    private User $managerB;

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
            $this->tenantB = Tenant::query()->create([
                'name' => 'Tenant B', 'slug' => 'tenant-b', 'email' => 'b@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = $this->branch($this->tenantA, 'North', 'NORTH');
            $this->branchB = $this->branch($this->tenantA, 'South', 'SOUTH');
            $this->branchOther = $this->branch($this->tenantB, 'Other', 'OTHER');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-a@example.com', $this->branchA);
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-a@example.com', $this->branchA);
            $this->managerB = $this->user($this->tenantB, RoleName::Manager, 'manager-b@example.com', $this->branchOther);
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-a@example.com', $this->branchA);
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-a@example.com', $this->branchA);
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-a@example.com', $this->branchA);
        });
    }

    public function test_documented_goods_flow_stays_inside_the_branch_and_permission_matrix(): void
    {
        $this->actingAs($this->managerA)->post('/commodities', [
            'name' => 'Potato', 'variety' => 'Lady Rosetta', 'grade' => 'A',
        ])->assertRedirect();
        $commodity = Commodity::query()->where('name', 'Potato')->firstOrFail();

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Ali Traders', 'credit_limit' => 8800.5,
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Ali Traders', 'credit_limit' => 8800.5,
        ])->assertRedirect();
        $customer = Customer::query()->where('name', 'Ali Traders')->firstOrFail();
        $this->assertSame($this->tenantA->id, $customer->tenant_id);
        $this->assertSame($this->branchA->id, $customer->branch_id);

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/rates', [
            'customer_id' => $customer->id, 'rate' => 12.5,
        ])->assertRedirect();

        $this->actingAs($this->gatekeeperA)->get('/customers')->assertOk()->assertDontSee('8800.50');
        $this->actingAs($this->gatekeeperA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/invoices')->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/customers', [
            'name' => 'Wrong branch',
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', [
            'name' => 'Chamber 1', 'code' => 'C1',
        ])->assertRedirect();
        $chamberId = \App\Models\Csms\Chamber::query()->where('code', 'C1')->value('id');
        $this->actingAs($this->managerA)->post('/chambers/'.$chamberId.'/racks', ['name' => 'Rack 1', 'code' => 'R1'])->assertRedirect();
        $rackId = \App\Models\Csms\Rack::query()->where('code', 'R1')->value('id');
        $this->actingAs($this->managerA)->post('/racks/'.$rackId.'/levels', ['name' => 'Level 1', 'code' => 'L1'])->assertRedirect();
        $levelId = \App\Models\Csms\Level::query()->where('code', 'L1')->value('id');
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S1'])->assertRedirect();
        $slotId = \App\Models\Csms\Slot::query()->where('code', 'S1')->value('id');

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 100,
        ])->assertRedirect();
        $booking = \App\Models\Csms\Booking::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/bookings/'.$booking->id.'/approve')->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/bookings/'.$booking->id.'/approve')->assertForbidden();

        $approver = $this->user($this->tenantA, RoleName::Manager, 'manager-2@example.com', $this->branchA);
        $this->actingAs($approver)->post('/bookings/'.$booking->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $booking->fresh()->status);

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'LES-100', 'purpose' => 'inbound', 'customer_id' => $customer->id, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $entry = GateEntry::query()->where('vehicle_no', 'LES-100')->firstOrFail();

        $this->actingAs($this->managerB)->get('/gates/'.$entry->id)->assertNotFound();

        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/weigh', ['kind' => 'gross', 'weight' => 1500])->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/inspect', ['result' => 'accept'])->assertRedirect();

        $this->actingAs($this->managerA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 100, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $ownReceipt = GoodsReceipt::query()->where('created_by', $this->managerA->id)->firstOrFail();
        $this->actingAs($this->managerA)->post('/grns/'.$ownReceipt->id.'/approve', ['chamber_id' => $chamberId])->assertForbidden();

        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 80, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $receipt = GoodsReceipt::query()->where('created_by', $this->supervisorA->id)->firstOrFail();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/weigh', ['kind' => 'tare', 'weight' => 500])->assertRedirect();
        $this->actingAs($approver)->post('/grns/'.$receipt->id.'/approve', ['chamber_id' => $chamberId])->assertRedirect();

        $lot = StorageLot::query()->where('goods_receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame('awaiting_putaway', $lot->status);
        $this->assertNotEmpty($lot->lot_number);
        $task = \App\Models\Csms\PutAwayTask::query()->where('storage_lot_id', $lot->id)->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slotId,
        ])->assertRedirect();
        $this->assertSame('stored', $lot->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/lots/'.$lot->id.'/deliveries', ['quantity' => 80])->assertRedirect();
        $order = \App\Models\Csms\DeliveryOrder::query()->firstOrFail();
        $this->actingAs($approver)->post('/delivery-orders/'.$order->id.'/approve')->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/delivery-orders/'.$order->id.'/pass')->assertRedirect();
        $pass = \App\Models\Csms\GatePass::query()->firstOrFail();
        $this->actingAs($this->gatekeeperA)->post('/gate-passes/'.$pass->id.'/release')->assertRedirect();
        $this->assertSame('released', $pass->fresh()->status);

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/invoices', [
            'customer_id' => $customer->id, 'delivery_order_id' => $order->id, 'amount' => 250,
        ])->assertRedirect();
        $invoice = StorageInvoice::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/post')->assertRedirect();
        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/post')->assertForbidden();
        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/payments', ['amount' => 250])->assertRedirect();
        $payment = \App\Models\Csms\StoragePayment::query()->firstOrFail();
        $this->assertSame('posted', $payment->status);
        $this->assertSame('closed', $lot->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/monitoring/readings', [
            'chamber_id' => $chamberId, 'temperature' => -2.5,
        ])->assertRedirect();
        $reading = SensorReading::query()->firstOrFail();

        try {
            $reading->update(['temperature' => 9]);
            $this->fail('A sensor reading was edited.');
        } catch (ImmutableRecordException) {
            $this->assertEquals(-2.5, (float) $reading->fresh()->temperature);
        }
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

    private function user(Tenant $tenant, RoleName $role, string $email, Branch $branch): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());
        $user->branches()->sync([$branch->id]);

        return $user;
    }
}
