<?php

namespace Tests\Feature\Notifications;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\InternalAlert;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Accounts\AccountsDesk;
use App\Services\Dispatch\DispatchDesk;
use App\Services\Notifications\OperationalAlertHooks;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchB;

    private User $managerA;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private User $managerB;

    private Customer $customer;

    private Commodity $commodity;

    private Chamber $chamber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Ops Co', 'slug' => 'ops-co', 'email' => 'ops@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->tenantB = Tenant::query()->create([
                'name' => 'Other Ops', 'slug' => 'other-ops', 'email' => 'other@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'ONORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $this->tenantB->id, 'name' => 'West', 'code' => 'OWEST', 'status' => BranchStatus::Active,
            ]);
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'mgr-ops@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'sup-ops@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-ops@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'acct-ops@example.com');
            $this->managerB = $this->user($this->tenantB, RoleName::Manager, 'mgr-b-ops@example.com');
            foreach ([$this->managerA, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $u) {
                $u->branches()->sync([$this->branchA->id]);
            }
            $this->managerB->branches()->sync([$this->branchB->id]);

            $this->customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'Ops Cust', 'status' => 'active', 'created_by' => $this->managerA->id,
            ]);
            $this->commodity = Commodity::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'Potato', 'status' => 'active',
            ]);
            $this->chamber = Chamber::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'Cold-1', 'code' => 'C1', 'capacity' => 100, 'capacity_unit' => 'tons',
                'status' => 'active', 'created_by' => $this->managerA->id,
            ]);
        });
    }

    public function test_chamber_occupancy_near_full_idempotent_and_role_filtered(): void
    {
        $this->storedLot(91, 'LOT-OCC-1');

        $hooks = app(OperationalAlertHooks::class);
        $hooks->checkChamberOccupancy($this->chamber->fresh());
        $hooks->checkChamberOccupancy($this->chamber->fresh());

        $alerts = app(TenantContext::class)->bypass(fn () => InternalAlert::query()
            ->where('category', 'chamber_occupancy')
            ->where('reference_id', $this->chamber->id)
            ->get());

        $this->assertTrue($alerts->pluck('user_id')->contains($this->managerA->id));
        $this->assertTrue($alerts->pluck('user_id')->contains($this->supervisorA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->gatekeeperA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->accountantA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->managerB->id));
        $this->assertSame(
            $alerts->where('user_id', $this->managerA->id)->count(),
            1,
            'duplicate near-full alerts must be suppressed'
        );
    }

    public function test_low_stock_dispatch_alert_without_bypassing_validation(): void
    {
        app(TenantContext::class)->resolveForTenant((int) $this->tenantA->id);
        $lot = $this->storedLot(10, 'LOT-LOW-1')->load('branch');
        $this->assertNotNull($lot->branch);

        $desk = app(DispatchDesk::class);
        try {
            $desk->open($this->supervisorA, $lot, 50);
            $this->fail('Expected abort for insufficient stock');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(0, DeliveryOrder::query()->count());

        $alerts = app(TenantContext::class)->bypass(fn () => InternalAlert::query()
            ->where('category', 'low_stock_dispatch')
            ->get());
        $this->assertTrue($alerts->pluck('user_id')->contains($this->managerA->id));
        $this->assertTrue($alerts->pluck('user_id')->contains($this->supervisorA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->gatekeeperA->id));
        $this->assertStringContainsString('LOT-LOW-1', $alerts->first()->body);
        $this->assertStringNotContainsString('credit', strtolower($alerts->first()->body));
        $this->assertStringNotContainsString('dues', strtolower($alerts->first()->body));

        try {
            $desk->open($this->supervisorA, $lot->fresh()->load('branch'), 50);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
        }
        $this->assertSame(
            1,
            app(TenantContext::class)->bypass(fn () => InternalAlert::query()
                ->where('category', 'low_stock_dispatch')
                ->where('user_id', $this->managerA->id)
                ->count())
        );
    }

    public function test_overdue_invoice_scan_command_idempotent_and_finance_recipients(): void
    {
        $invoice = app(TenantContext::class)->bypass(fn () => StorageInvoice::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-OD-1',
            'kind' => 'tax',
            'invoice_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'amount' => 500,
            'subtotal' => 500,
            'status' => 'posted',
            'posted_at' => now()->subDays(40),
            'created_by' => $this->accountantA->id,
        ]));

        $this->artisan('csms:scan-overdue-invoices')->assertSuccessful();
        $this->artisan('csms:scan-overdue-invoices')->assertSuccessful();

        $alerts = app(TenantContext::class)->bypass(fn () => InternalAlert::query()
            ->where('category', 'overdue_invoice')
            ->where('reference_id', $invoice->id)
            ->get());

        $this->assertTrue($alerts->pluck('user_id')->contains($this->accountantA->id));
        $this->assertTrue($alerts->pluck('user_id')->contains($this->managerA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->gatekeeperA->id));
        $this->assertFalse($alerts->pluck('user_id')->contains($this->managerB->id));
        $this->assertSame(1, $alerts->where('user_id', $this->accountantA->id)->count());

        $before = $invoice->fresh()->only(['amount', 'status', 'due_date']);
        $this->assertSame('posted', $before['status']);
        $this->assertEquals(500, (float) $before['amount']);
    }

    public function test_pending_approval_alerts_for_delivery_and_expense(): void
    {
        app(TenantContext::class)->resolveForTenant((int) $this->tenantA->id);
        $lot = $this->storedLot(40, 'LOT-PA-1')->load('branch');
        $this->assertNotNull($lot->branch);

        $order = app(DispatchDesk::class)->open($this->supervisorA, $lot, 10, 'fifo', true);
        $this->assertSame('pending', $order->status);

        $doAlerts = app(TenantContext::class)->bypass(fn () => InternalAlert::query()
            ->where('category', 'pending_approval')
            ->where('reference_type', DeliveryOrder::class)
            ->where('reference_id', $order->id)
            ->get());
        $this->assertTrue($doAlerts->pluck('user_id')->contains($this->managerA->id));
        $this->assertFalse($doAlerts->pluck('user_id')->contains($this->gatekeeperA->id));

        // Duplicate pending for same order suppressed
        app(OperationalAlertHooks::class)->pendingApproval(
            $order,
            (int) $order->tenant_id,
            (int) $order->branch_id,
            'Delivery order',
            'retry'
        );
        $this->assertSame(
            1,
            app(TenantContext::class)->bypass(fn () => InternalAlert::query()
                ->where('category', 'pending_approval')
                ->where('reference_id', $order->id)
                ->where('user_id', $this->managerA->id)
                ->count())
        );

        $expense = app(AccountsDesk::class)->createExpense($this->accountantA, $this->branchA, [
            'description' => 'Petrol',
            'amount' => 25,
            'expense_date' => now()->toDateString(),
            'kind' => 'expense',
        ]);
        $this->assertInstanceOf(OperatingExpense::class, $expense);

        $expAlerts = app(TenantContext::class)->bypass(fn () => InternalAlert::query()
            ->where('category', 'pending_approval')
            ->where('reference_type', OperatingExpense::class)
            ->where('reference_id', $expense->id)
            ->get());
        $this->assertNotEmpty($expAlerts);
        $this->assertFalse($expAlerts->pluck('user_id')->contains($this->gatekeeperA->id));
    }

    public function test_gatekeeper_cannot_view_others_operational_alerts(): void
    {
        $alert = app(TenantContext::class)->bypass(fn () => InternalAlert::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'user_id' => $this->managerA->id,
            'category' => 'overdue_invoice',
            'title' => 'Secret finance',
            'body' => 'Balance details',
            'severity' => 'warning',
            'idempotency_key' => 'test-finance-'.$this->managerA->id,
        ]));

        $this->actingAs($this->gatekeeperA)
            ->post('/notifications/alerts/'.$alert->id.'/read')
            ->assertForbidden();
    }

    private function storedLot(float $qty, string $lotNumber): StorageLot
    {
        return app(TenantContext::class)->bypass(function () use ($qty, $lotNumber): StorageLot {
            $gate = GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'vehicle_no' => 'OPS-'.substr($lotNumber, -4), 'purpose' => 'inbound',
                'customer_id' => $this->customer->id, 'status' => 'inside',
                'created_by' => $this->gatekeeperA->id,
            ]);
            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id, 'customer_id' => $this->customer->id,
                'commodity_id' => $this->commodity->id, 'chamber_id' => $this->chamber->id,
                'quantity' => $qty, 'status' => 'approved', 'created_by' => $this->supervisorA->id,
            ]);

            return StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $receipt->id, 'chamber_id' => $this->chamber->id,
                'customer_id' => $this->customer->id, 'commodity_id' => $this->commodity->id,
                'lot_number' => $lotNumber, 'quantity' => $qty, 'status' => 'stored',
                'received_at' => now(),
            ]);
        });
    }

    private function user(Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}
