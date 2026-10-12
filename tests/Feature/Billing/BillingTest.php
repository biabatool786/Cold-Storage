<?php

namespace Tests\Feature\Billing;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\BillingAdjustment;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\RateAgreement;
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

class BillingTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Bill Co', 'slug' => 'bill-co', 'email' => 'bill@example.com', 'status' => TenantStatus::Active,
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
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
            $commodity = Commodity::query()->create([
                'tenant_id' => $this->tenantA->id,
                'name' => 'Potato',
                'created_by' => $this->managerA->id,
            ]);
            $chamber = Chamber::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'C1',
                'code' => 'C1',
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
            $gate = GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'vehicle_no' => 'BILL-1',
                'purpose' => 'inbound',
                'customer_id' => $this->customer->id,
                'status' => 'inside',
                'created_by' => $this->gatekeeperA->id,
            ]);
            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id,
                'customer_id' => $this->customer->id,
                'commodity_id' => $commodity->id,
                'chamber_id' => $chamber->id,
                'quantity' => 100,
                'status' => 'approved',
                'created_by' => $this->supervisorA->id,
            ]);
            $this->lot = StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $receipt->id,
                'chamber_id' => $chamber->id,
                'customer_id' => $this->customer->id,
                'commodity_id' => $commodity->id,
                'lot_number' => 'LOT-BILL-1',
                'quantity' => 100,
                'status' => 'stored',
                'received_at' => now()->subDays(20),
            ]);
        });
    }

    public function test_billing_matrix_charges_and_posted_immutability(): void
    {
        $this->actingAs($this->gatekeeperA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->adminA)->get('/invoices')->assertOk();
        $this->actingAs($this->managerA)->get('/invoices')->assertOk();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/rates', [
            'customer_id' => $this->customer->id,
            'rate' => 2,
            'unit' => 'kg',
            'free_days' => 3,
            'grace_days' => 2,
            'tax_percent' => 10,
            'charge_type' => 'storage',
        ])->assertRedirect();
        $rate = RateAgreement::query()->firstOrFail();
        $this->assertSame('pending', $rate->approval_status);
        $this->actingAs($this->managerA)->post('/billing/tariffs/'.$rate->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->manager2)->post('/billing/tariffs/'.$rate->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->assertSame('approved', $rate->fresh()->approval_status);

        $this->actingAs($this->adminA)->post('/billing/branches/'.$this->branchA->id.'/settings', [
            'default_tax_percent' => 5,
            'default_free_days' => 1,
            'default_grace_days' => 1,
            'demurrage_rate' => 10,
            'late_fee_percent' => 2,
        ])->assertRedirect();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/invoices', [
            'customer_id' => $this->customer->id, 'amount' => 100, 'kind' => 'tax',
        ])->assertForbidden();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/invoices', [
            'customer_id' => $this->customer->id,
            'storage_lot_id' => $this->lot->id,
            'kind' => 'tax',
        ])->assertRedirect();
        $invoice = StorageInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->amount);
        $this->assertTrue($invoice->lines()->exists());

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/invoices', [
            'customer_id' => $this->customer->id,
            'storage_lot_id' => $this->lot->id,
            'kind' => 'tax',
        ])->assertSessionHasErrors();

        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/discount', [
            'percent' => 3, 'reason' => 'Loyalty',
        ])->assertRedirect();
        $this->assertSame('approved', $invoice->fresh()->discount_status);

        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/discount', [
            'percent' => 20, 'reason' => 'Above limit',
        ])->assertRedirect();
        $this->assertSame('pending', $invoice->fresh()->discount_status);
        $this->actingAs($this->managerA)->post('/invoices/'.$invoice->id.'/discount/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/invoices/'.$invoice->id.'/discount/decide', ['approve' => 1])->assertRedirect();

        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/post')->assertRedirect();
        $this->assertSame('posted', $invoice->fresh()->status);

        $this->actingAs($this->accountantA)->put('/invoices/'.$invoice->id, ['notes' => 'hack'])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/post')->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/invoices/'.$invoice->id)->assertStatus(405);

        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/adjustment', [
            'kind' => 'credit', 'amount' => 25, 'reason' => 'Rate correction',
        ])->assertRedirect();
        $note = BillingAdjustment::query()->firstOrFail();
        $this->assertSame('pending', $note->status);
        $this->actingAs($this->accountantA)->post('/billing/adjustments/'.$note->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/billing/adjustments/'.$note->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->assertEquals(25, (float) $invoice->fresh()->credited_amount);

        $this->actingAs($this->accountantA)->post('/invoices/'.$invoice->id.'/cancel', [
            'reason' => 'Duplicate billing',
        ])->assertRedirect();
        $this->assertSame('pending', $invoice->fresh()->cancel_status);
        $this->actingAs($this->managerA)->post('/invoices/'.$invoice->id.'/cancel/decide', ['approve' => 1])->assertRedirect();
        $this->assertSame('cancelled', $invoice->fresh()->status);

        $this->actingAs($this->accountantA)->post('/billing/branches/'.$this->branchA->id.'/bulk', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/billing/branches/'.$this->branchA->id.'/bulk', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertSessionHasErrors();

        $this->actingAs($this->supervisorA)->get('/invoices/'.$invoice->id.'/print')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'billing.invoice_printed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'invoice.posted')->exists());
        $this->actingAs($this->accountantA)->get('/billing/accrued')->assertOk()->assertSee('LOT-BILL-1');
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
