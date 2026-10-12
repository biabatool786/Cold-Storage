<?php

namespace Tests\Feature\Receivables;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\BadDebtWriteOff;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\CustomerDeposit;
use App\Models\Csms\DepositRefundRequest;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\PaymentAllocation;
use App\Models\Csms\StockLoan;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Receivables\ReceivablesDesk;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivablesTest extends TestCase
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

    private Customer $customer;

    private StorageLot $lot;

    private StorageInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'AR Co', 'slug' => 'ar-co', 'email' => 'ar@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active,
            ]);
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
            $this->customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Acme Cold',
                'email' => 'acme@example.com',
                'status' => 'approved',
                'created_by' => $this->managerA->id,
            ]);
            $commodity = Commodity::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'Potato', 'created_by' => $this->managerA->id,
            ]);
            $chamber = Chamber::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'C1', 'code' => 'C1', 'status' => 'active', 'created_by' => $this->managerA->id,
            ]);
            $gate = GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'vehicle_no' => 'AR-1', 'purpose' => 'inbound', 'customer_id' => $this->customer->id,
                'status' => 'inside', 'created_by' => $this->gatekeeperA->id,
            ]);
            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id, 'customer_id' => $this->customer->id,
                'commodity_id' => $commodity->id, 'chamber_id' => $chamber->id,
                'quantity' => 100, 'status' => 'approved', 'created_by' => $this->supervisorA->id,
            ]);
            $this->lot = StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $receipt->id, 'chamber_id' => $chamber->id,
                'customer_id' => $this->customer->id, 'commodity_id' => $commodity->id,
                'lot_number' => 'LOT-AR-1', 'quantity' => 100, 'status' => 'stored',
                'received_at' => now()->subDays(20),
            ]);
            $this->invoice = StorageInvoice::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'customer_id' => $this->customer->id,
                'storage_lot_id' => $this->lot->id,
                'number' => 'INV-AR-1',
                'kind' => 'tax',
                'invoice_date' => now()->subDays(40)->toDateString(),
                'due_date' => now()->subDays(10)->toDateString(),
                'amount' => 1000,
                'subtotal' => 1000,
                'status' => 'posted',
                'posted_at' => now()->subDays(40),
                'created_by' => $this->accountantA->id,
            ]);
        });
    }

    public function test_role_matrix_and_unauthorized_actions(): void
    {
        $this->actingAs($this->gatekeeperA)->get('/payments')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/payments')->assertOk();
        $this->actingAs($this->supervisorA)->get('/payments/aging/print')->assertForbidden();
        $this->actingAs($this->adminA)->get('/payments')->assertOk();
        $this->actingAs($this->adminA)->get('/payments/aging/print')->assertForbidden();
        $this->actingAs($this->managerA)->get('/payments')->assertOk();
        $this->actingAs($this->managerA)->get('/payments/create')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/payments/create')->assertOk();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id, 'amount' => 10, 'method' => 'cash',
            'paid_on' => now()->toDateString(), 'kind' => 'invoice',
        ])->assertForbidden();
    }

    public function test_payment_allocation_duplicate_and_over_allocation(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 400,
            'method' => 'cash',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'CASH-1',
        ])->assertRedirect();

        $payment = StoragePayment::query()->where('reference', 'CASH-1')->firstOrFail();
        $this->assertSame('600.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'amount' => 50,
            'method' => 'bank',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'CASH-1',
        ])->assertSessionHasErrors('reference');

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'amount' => 300,
            'method' => 'bank',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'BANK-2',
            'allocate' => 0,
        ])->assertRedirect();
        $p2 = StoragePayment::query()->where('reference', 'BANK-2')->firstOrFail();

        $this->actingAs($this->accountantA)->post('/payments/'.$p2->id.'/allocate', [
            'allocations' => [
                ['invoice_id' => $this->invoice->id, 'amount' => 700],
            ],
        ])->assertSessionHasErrors('allocations');

        $this->actingAs($this->accountantA)->post('/payments/'.$p2->id.'/allocate', [
            'allocations' => [
                ['invoice_id' => $this->invoice->id, 'amount' => 200],
            ],
        ])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/'.$p2->id.'/allocate', [
            'allocations' => [
                ['invoice_id' => $this->invoice->id, 'amount' => 50],
            ],
        ])->assertSessionHasErrors('allocations');

        $this->assertEquals(2, PaymentAllocation::query()->count());
        $this->assertSame('400.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));
        $this->assertTrue(AuditLog::query()->where('action', 'payment.recorded')->exists());
    }

    public function test_cheque_bounced_not_cleared_funds_and_same_day_edit(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 500,
            'method' => 'cheque',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'cheque_number' => 'CHQ-9',
            'reference' => 'CHQ-9',
        ])->assertRedirect();

        $payment = StoragePayment::query()->where('cheque_number', 'CHQ-9')->firstOrFail();
        $this->assertSame('deposited', $payment->cheque_status);
        $this->assertSame('1000.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));

        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/cheque', ['status' => 'cleared'])->assertRedirect();
        $this->assertSame('500.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));

        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/cheque', ['status' => 'bounced'])->assertRedirect();
        $this->assertSame('1000.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));

        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/edit', [
            'notes' => 'Same day note',
        ])->assertRedirect();
        $this->assertSame('Same day note', $payment->fresh()->notes);

        $payment->update(['paid_on' => now()->subDay()->toDateString()]);
        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/edit', [
            'notes' => 'Old',
        ])->assertStatus(422);
    }

    public function test_deposit_refund_maker_checker_and_limits(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'amount' => 200,
            'method' => 'cash',
            'paid_on' => now()->toDateString(),
            'kind' => 'security',
            'reference' => 'DEP-1',
        ])->assertRedirect();

        $deposit = CustomerDeposit::query()->firstOrFail();
        $this->assertSame(200.0, $deposit->remainingBalance());

        $this->actingAs($this->accountantA)->post('/payments/deposits/'.$deposit->id.'/refund', [
            'amount' => 250, 'reason' => 'Too much',
        ])->assertStatus(422);

        $this->actingAs($this->accountantA)->post('/payments/deposits/'.$deposit->id.'/refund', [
            'amount' => 80, 'reason' => 'Partial refund',
        ])->assertRedirect();

        $req = DepositRefundRequest::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/payments/refunds/'.$req->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/payments/refunds/'.$req->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->actingAs($this->managerA)->post('/payments/refunds/'.$req->id.'/execute')->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/payments/refunds/'.$req->id.'/execute')->assertRedirect();
        $this->assertSame(120.0, $deposit->fresh()->remainingBalance());
        $this->assertSame('executed', $req->fresh()->status);
    }

    public function test_reversal_and_bad_debt_workflows(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 100,
            'method' => 'cash',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'REV-1',
        ])->assertRedirect();
        $payment = StoragePayment::query()->where('reference', 'REV-1')->firstOrFail();

        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/reverse', ['reason' => 'Wrong amount'])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/reverse/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/payments/'.$payment->id.'/reverse/decide', ['approve' => 1])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/'.$payment->id.'/reverse/execute')->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/payments/'.$payment->id.'/reverse/execute')->assertRedirect();
        $this->assertTrue($payment->fresh()->isReversed());
        $this->assertSame('1000.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));
        $this->assertDatabaseHas('storage_payments', ['id' => $payment->id]);

        $this->actingAs($this->accountantA)->post('/payments/write-offs', [
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 100,
            'reason' => 'Uncollectible',
        ])->assertRedirect();
        $wo = BadDebtWriteOff::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/payments/write-offs/'.$wo->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/payments/write-offs/'.$wo->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->assertSame('executed', $wo->fresh()->status);
        $this->assertSame('900.00', number_format(app(ReceivablesDesk::class)->invoiceOutstanding($this->invoice->fresh()), 2, '.', ''));
    }

    public function test_ledger_aging_holds_reminders_and_loans(): void
    {
        $desk = app(ReceivablesDesk::class);
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 250,
            'method' => 'cash',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'LED-1',
        ])->assertRedirect();

        $this->actingAs($this->supervisorA)->get('/payments/ledger?customer_id='.$this->customer->id)->assertOk();
        $this->actingAs($this->supervisorA)->get('/payments/ledger/'.$this->customer->id.'/print')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/payments/ledger/'.$this->customer->id.'/print')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'payment.statement_exported')->exists());

        $this->actingAs($this->managerA)->get('/payments/aging')->assertOk()->assertSee('Acme Cold');
        $aging = $desk->receivableAging($this->managerA);
        $this->assertNotEmpty($aging);
        $this->assertEqualsWithDelta(750, $aging[0]['total'], 0.01);

        $this->actingAs($this->gatekeeperA)->post('/payments/customers/'.$this->customer->id.'/hold', [
            'reason' => 'dues',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/payments/customers/'.$this->customer->id.'/hold', [
            'reason' => 'Outstanding dues hold',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/payments/customers/'.$this->customer->id.'/hold', [
            'reason' => 'Outstanding dues hold',
        ])->assertRedirect();
        $this->assertSame('hold', $this->customer->fresh()->status);

        $this->actingAs($this->accountantA)->post('/payments/customers/'.$this->customer->id.'/remind', [
            'message' => 'Please pay',
        ])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'payment.reminder_failed')->exists());

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments/loans', [
            'customer_id' => $this->customer->id,
            'storage_lot_id' => $this->lot->id,
            'amount' => 500,
            'notes' => 'Against stock',
        ])->assertRedirect();
        $loan = StockLoan::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/payments/loans/'.$loan->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/payments/loans/'.$loan->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/loans/'.$loan->id.'/repay', ['amount' => 500])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/loans/'.$loan->id.'/close')->assertRedirect();
        $this->actingAs($this->manager2)->post('/payments/loans/'.$loan->id.'/close/decide', ['approve' => 1])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/payments/loans/'.$loan->id.'/close/execute')->assertRedirect();
        $this->assertSame('closed', $loan->fresh()->status);
        $this->assertSame(100.0, (float) $this->lot->fresh()->quantity);
    }

    public function test_receipt_print_and_tenant_isolation(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/payments', [
            'customer_id' => $this->customer->id,
            'storage_invoice_id' => $this->invoice->id,
            'amount' => 10,
            'method' => 'cash',
            'paid_on' => now()->toDateString(),
            'kind' => 'invoice',
            'reference' => 'RCP-1',
        ])->assertRedirect();
        $payment = StoragePayment::query()->where('reference', 'RCP-1')->firstOrFail();
        $this->actingAs($this->accountantA)->get('/payments/'.$payment->id.'/receipt')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'payment.receipt_printed')->exists());

        $userB = null;
        app(TenantContext::class)->bypass(function () use (&$userB): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other', 'slug' => 'other-ar', 'email' => 'ob@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchB = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'South', 'code' => 'SOUTH', 'status' => BranchStatus::Active,
            ]);
            $userB = $this->user($tenantB, RoleName::Accountant, 'acct-b@example.com');
            $userB->branches()->sync([$branchB->id]);
        });
        $this->actingAs($userB)->get('/payments/'.$payment->id)->assertNotFound();
    }

    private function user(?Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}
