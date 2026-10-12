<?php

namespace Tests\Feature\Accounts;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\AccountingPeriod;
use App\Models\Csms\BankStatementLine;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\Vendor;
use App\Models\Csms\VendorBill;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountsTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Exp Co', 'slug' => 'exp-co', 'email' => 'exp@example.com', 'status' => TenantStatus::Active,
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
        });
    }

    public function test_expense_matrix_limits_maker_checker_and_attachments(): void
    {
        Storage::fake();
        $this->actingAs($this->gatekeeperA)->get('/expenses')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/expenses')->assertOk();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'General repair', 'amount' => 100, 'expense_date' => now()->toDateString(), 'kind' => 'expense',
        ])->assertForbidden();

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'Taxi', 'amount' => 40, 'expense_date' => now()->toDateString(), 'kind' => 'petty_cash_request',
        ])->assertRedirect();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'Power bill', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'kind' => 'expense', 'reference' => 'EXP-1',
        ])->assertRedirect();
        $expense = OperatingExpense::query()->where('reference', 'EXP-1')->firstOrFail();

        $this->actingAs($this->accountantA)->post('/expenses/'.$expense->id.'/attach', [
            'receipt' => UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseCount('expense_attachments', 1);
        $attId = \App\Models\Csms\ExpenseAttachment::query()->first()->id;
        $this->actingAs($this->gatekeeperA)->get('/expense-attachments/'.$attId)->assertForbidden();
        $this->actingAs($this->accountantA)->get('/expense-attachments/'.$attId)->assertOk();

        $this->actingAs($this->accountantA)->post('/expenses/'.$expense->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/expenses/'.$expense->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $expense->fresh()->status);

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'Big capex', 'amount' => 9000, 'expense_date' => now()->toDateString(),
            'kind' => 'expense', 'reference' => 'EXP-BIG',
        ])->assertRedirect();
        $big = OperatingExpense::query()->where('reference', 'EXP-BIG')->firstOrFail();
        $this->actingAs($this->manager2)->post('/expenses/'.$big->id.'/approve')->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/expenses/'.$big->id.'/approve')->assertRedirect();

        $this->actingAs($this->accountantA)->post('/expenses/'.$expense->id.'/post')->assertRedirect();
        $this->assertSame('posted', $expense->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'expense.posted')->exists());
    }

    public function test_vendors_bills_payments_and_duplicates(): void
    {
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/vendors', [
            'name' => 'Cool Parts',
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/vendors', [
            'name' => 'Cool Parts',
        ])->assertRedirect();
        $vendor = Vendor::query()->firstOrFail();

        $this->actingAs($this->accountantA)->post('/vendors/'.$vendor->id.'/bills', [
            'bill_date' => now()->toDateString(),
            'reference' => 'VB-1',
            'amount' => 500,
        ])->assertRedirect();
        $this->actingAs($this->accountantA)->post('/vendors/'.$vendor->id.'/bills', [
            'bill_date' => now()->toDateString(),
            'reference' => 'VB-1',
            'amount' => 100,
        ])->assertSessionHasErrors('reference');

        $bill = VendorBill::query()->firstOrFail();
        $this->assertSame('open', $bill->status);
        $this->actingAs($this->accountantA)->post('/vendors/'.$vendor->id.'/payments', [
            'vendor_bill_id' => $bill->id,
            'amount' => 200,
            'paid_on' => now()->toDateString(),
            'method' => 'bank',
            'reference' => 'VP-1',
        ])->assertRedirect();
        $this->assertSame('partial', $bill->fresh()->status);
        $this->assertEqualsWithDelta(300, $bill->fresh()->outstanding(), 0.01);

        $this->actingAs($this->accountantA)->post('/vendors/'.$vendor->id.'/payments', [
            'vendor_bill_id' => $bill->id,
            'amount' => 300,
            'paid_on' => now()->toDateString(),
            'method' => 'cash',
            'reference' => 'VP-2',
        ])->assertRedirect();
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_bank_recon_reports_export_and_period_close(): void
    {
        $this->actingAs($this->managerA)->get('/accounts/bank')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/accounts/profit-loss')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/accounts/day-book')->assertOk();
        $this->actingAs($this->managerA)->get('/accounts/day-book')->assertForbidden();
        $this->actingAs($this->managerA)->get('/accounts/profit-loss')->assertOk();
        $this->actingAs($this->managerA)->get('/accounts/petty-cash')->assertOk();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/accounts/bank/lines', [
            'statement_date' => now()->toDateString(),
            'description' => 'Transfer',
            'amount' => 50,
            'reference' => 'BS-1',
        ])->assertRedirect();
        $line = BankStatementLine::query()->firstOrFail();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'Matched opex', 'amount' => 50, 'expense_date' => now()->toDateString(),
            'kind' => 'expense', 'reference' => 'EXP-M',
        ])->assertRedirect();
        $exp = OperatingExpense::query()->where('reference', 'EXP-M')->firstOrFail();
        $this->actingAs($this->managerA)->post('/expenses/'.$exp->id.'/approve')->assertRedirect();
        $this->actingAs($this->accountantA)->post('/expenses/'.$exp->id.'/post')->assertRedirect();

        $this->actingAs($this->accountantA)->post('/accounts/bank/lines/'.$line->id.'/match', [
            'matched_type' => 'operating_expense',
            'matched_id' => $exp->id,
        ])->assertRedirect();
        $this->assertSame('matched', $line->fresh()->match_status);

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/accounts/bank/complete', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/accounts/export', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'accounts.export_unavailable')->exists());

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/accounts/periods/close', [
            'period_start' => $start,
            'period_end' => $end,
        ])->assertRedirect();
        $period = AccountingPeriod::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/accounts/periods/'.$period->id.'/decide', ['approve' => 1])->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/accounts/periods/'.$period->id.'/decide', ['approve' => 1])->assertRedirect();
        $this->assertSame('closed', $period->fresh()->status);

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'After close', 'amount' => 10, 'expense_date' => now()->toDateString(),
            'kind' => 'expense', 'reference' => 'EXP-CLOSED',
        ])->assertSessionHasErrors('period');

        $this->actingAs($this->managerA)->post('/accounts/periods/'.$period->id.'/reopen', [
            'reason' => 'Correction needed',
        ])->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/accounts/periods/'.$period->id.'/reopen', [
            'reason' => 'Correction needed',
        ])->assertRedirect();
        $this->assertSame('open', $period->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'accounts.period_reopened')->exists());
    }

    public function test_tenant_isolation_on_expenses(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/expenses', [
            'description' => 'Isolated', 'amount' => 15, 'expense_date' => now()->toDateString(),
            'kind' => 'expense', 'reference' => 'EXP-ISO',
        ])->assertRedirect();
        $expense = OperatingExpense::query()->where('reference', 'EXP-ISO')->firstOrFail();

        $userB = null;
        app(TenantContext::class)->bypass(function () use (&$userB): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other', 'slug' => 'other-exp', 'email' => 'oe@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchB = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'South', 'code' => 'SOUTH', 'status' => BranchStatus::Active,
            ]);
            $userB = $this->user($tenantB, RoleName::Accountant, 'acct-b@example.com');
            $userB->branches()->sync([$branchB->id]);
        });
        $this->actingAs($userB)->get('/expenses/'.$expense->id)->assertNotFound();
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
