<?php

namespace Tests\Feature\Security;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Customer;
use App\Models\Csms\StorageInvoice;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Security\Totp;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAndCustomerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

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
            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A', 'slug' => 'tenant-a', 'email' => 'a@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active,
            ]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-a@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-a@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-a@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-a@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-a@example.com');
            foreach ([$this->managerA, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
        });
    }

    public function test_account_administration_follows_the_matrix(): void
    {
        $this->actingAs($this->supervisorA)->post('/users', [
            'name' => 'Nope', 'email' => 'nope@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => RoleName::Gatekeeper->value,
        ])->assertForbidden();

        $this->actingAs($this->adminA)->post('/users', [
            'name' => 'Floor', 'email' => 'floor@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => RoleName::Gatekeeper->value,
        ])->assertRedirect();

        $floor = User::query()->where('email', 'floor@example.com')->firstOrFail();
        $this->actingAs($this->adminA)->put('/users/'.$floor->id, [
            'name' => 'Floor Lead', 'email' => 'floor@example.com', 'phone' => '0300',
        ])->assertRedirect();
        $this->assertSame('Floor Lead', $floor->fresh()->name);

        $this->actingAs($this->adminA)->post('/users/'.$this->superAdmin->id.'/status', ['status' => 'INACTIVE'])->assertNotFound();
        $this->actingAs($this->adminA)->post('/users/'.$floor->id.'/status', ['status' => 'INACTIVE'])->assertRedirect();
        $this->assertSame(UserStatus::Inactive, $floor->fresh()->status);
        $this->actingAs($floor)->post('/logout');
        $this->post('/login', ['email' => 'floor@example.com', 'password' => 'password'])->assertSessionHasErrors('email');

        $this->actingAs($this->adminA)->post('/users/'.$floor->id.'/password', [
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('password', $floor->fresh()->password));
        $this->assertTrue(AuditLog::query()->where('action', 'user.password_reset')->exists());
        $this->actingAs($this->managerA)->post('/users/'.$floor->id.'/password', [
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertForbidden();

        $floor->update(['locked_at' => now(), 'status' => UserStatus::Active]);
        $this->actingAs($this->managerA)->post('/users/'.$floor->id.'/unlock')->assertForbidden();
        $this->actingAs($this->adminA)->post('/users/'.$floor->id.'/unlock')->assertRedirect();
        $this->assertNull($floor->fresh()->locked_at);

        $this->actingAs($this->adminA)->post('/users/'.$floor->id.'/logout')->assertRedirect();
        $this->assertNotNull($floor->fresh()->session_invalidated_at);
        $this->actingAs($floor->fresh())->get('/dashboard')->assertRedirect('/login');

        $this->actingAs($this->managerA)->get('/login-history')->assertOk();
        $this->actingAs($this->supervisorA)->get('/login-history')->assertForbidden();
        $this->actingAs($this->adminA)->get('/login-history/export')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'login_history.exported')->exists());

        Setting::query()->updateOrCreate(['key' => 'password_min_length'], ['value' => '12']);
        $this->actingAs($this->adminA)->post('/users', [
            'name' => 'Short', 'email' => 'short@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => RoleName::Supervisor->value,
        ])->assertSessionHasErrors('password');

        Setting::query()->updateOrCreate(['key' => 'session_timeout_minutes'], ['value' => '5']);
        $this->actingAs($this->managerA)->withSession([
            'authenticated_at' => time(),
            'last_activity' => time() - 600,
        ])->get('/dashboard')->assertRedirect('/login');

        Setting::query()->updateOrCreate(['key' => 'two_factor_mode'], ['value' => 'optional']);
        $secret = app(Totp::class)->secret();
        $this->adminA->update(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $this->post('/logout');
        $this->post('/login', ['email' => 'admin-a@example.com', 'password' => 'password'])->assertRedirect('/two-factor');
        $this->post('/two-factor', ['code' => app(Totp::class)->verify($secret, '000000') ? '000000' : $this->currentCode($secret)])
            ->assertRedirect('/dashboard');

        Setting::query()->updateOrCreate(['key' => 'ip_restriction_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'ip_allowlist'], ['value' => '10.1.1.1']);
        $this->post('/logout');
        $this->post('/login', ['email' => 'manager-a@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertTrue(LoginEvent::query()->where('action', 'ip_blocked')->exists());

        Setting::query()->where('key', 'ip_restriction_enabled')->update(['value' => '0']);
        $this->actingAs($this->managerA)->put('/users/'.$this->managerA->id.'/role', ['role' => RoleName::Admin->value])->assertForbidden();
    }

    public function test_customer_master_follows_the_matrix(): void
    {
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/customers', ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Ali Traders', 'phone' => '111', 'credit_limit' => 5000,
        ])->assertRedirect();
        $customer = Customer::query()->where('name', 'Ali Traders')->firstOrFail();
        $this->assertSame('pending', $customer->status);
        $this->assertSame('0.00', (string) $customer->credit_limit);

        $this->actingAs($this->managerA)->post('/customers/'.$customer->id.'/approve')->assertForbidden();
        $this->actingAs($this->adminA)->post('/customers/'.$customer->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $customer->fresh()->status);

        $this->actingAs($this->gatekeeperA)->get('/customers')->assertOk()->assertSee('Ali Traders')->assertDontSee('Credit limit');
        $this->actingAs($this->supervisorA)->get('/customers/'.$customer->id)->assertOk()->assertDontSee('Billing');
        $this->actingAs($this->accountantA)->put('/customers/'.$customer->id, [
            'name' => 'Changed', 'reason' => 'Fix',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->put('/customers/'.$customer->id, [
            'billing_name' => 'Ali Billing', 'tax_no' => 'TAX1', 'reason' => 'Invoice name',
        ])->assertRedirect();

        Storage::fake();
        $this->actingAs($this->supervisorA)->post('/customers/'.$customer->id.'/documents', [
            'document' => UploadedFile::fake()->create('kyc.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/customers/'.$customer->id.'/documents', [
            'document' => UploadedFile::fake()->create('kyc.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->get('/customer-documents/'.\App\Models\Csms\CustomerDocument::query()->first()->id)->assertOk();

        $this->actingAs($this->accountantA)->post('/customers/'.$customer->id.'/credit', [
            'credit_limit' => 2500, 'reason' => 'Season',
        ])->assertRedirect();
        $change = \App\Models\Csms\CreditLimitChange::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/credit-limits/'.$change->id.'/approve')->assertForbidden();
        $this->actingAs($this->managerA)->post('/credit-limits/'.$change->id.'/approve')->assertRedirect();
        $this->assertSame('2500.00', (string) $customer->fresh()->credit_limit);

        $this->actingAs($this->managerA)->post('/customers/'.$customer->id.'/hold', [
            'status' => 'hold', 'reason' => 'Overdue',
        ])->assertRedirect();
        StorageInvoice::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_id' => $customer->id,
            'amount' => 100,
            'status' => 'posted',
            'created_by' => $this->accountantA->id,
        ]);
        $this->actingAs($this->accountantA)->post('/customers/'.$customer->id.'/release', ['reason' => 'Paid'])->assertForbidden();
        $this->actingAs($this->adminA)->post('/customers/'.$customer->id.'/release', ['reason' => 'Clear'])->assertForbidden();
        $this->actingAs($this->managerA)->post('/customers/'.$customer->id.'/release', ['reason' => 'Cleared by manager'])->assertRedirect();

        $this->actingAs($this->adminA)->delete('/customers/'.$customer->id, ['reason' => 'Duplicate'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/customers/'.$customer->id, ['reason' => 'Duplicate'])->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers/import', [
            'file' => UploadedFile::fake()->createWithContent('customers.csv', "name,phone\nImported,222\n"),
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/customers/import', [
            'file' => UploadedFile::fake()->createWithContent('customers.csv', "name,phone\nImported,222\n"),
        ])->assertRedirect();
        $this->assertNotNull(Customer::query()->where('name', 'Imported')->first());
        $this->actingAs($this->supervisorA)->get('/customers/export')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/customers/export')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'customer.exported')->exists());

        $other = app(TenantContext::class)->bypass(fn () => Branch::query()->create([
            'tenant_id' => Tenant::query()->create([
                'name' => 'Other', 'slug' => 'other', 'email' => 'o@example.com', 'status' => TenantStatus::Active,
            ])->id,
            'name' => 'Other', 'code' => 'OTHER', 'status' => BranchStatus::Active,
        ]));
        $this->actingAs($this->adminA)->post('/branches/'.$other->id.'/customers', ['name' => 'Cross'])->assertNotFound();
    }

    private function currentCode(string $secret): string
    {
        $totp = new \ReflectionClass(Totp::class);
        $method = $totp->getMethod('code');
        $method->setAccessible(true);

        return $method->invoke(app(Totp::class), $secret, (int) floor(time() / 30));
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
