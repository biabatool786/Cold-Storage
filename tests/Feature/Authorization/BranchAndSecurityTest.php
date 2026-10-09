<?php

namespace Tests\Feature\Authorization;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\ApprovalLimit;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\AssignUserRole;
use App\Services\Authorization\FieldAccess;
use App\Services\Authorization\FinancialDocumentGuard;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Authorization\RemapLegacyRoles;
use App\Services\Authorization\RestoreAuthorization;
use App\Services\Authorization\ShiftEditWindow;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BranchAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchB;

    private Branch $branchOther;

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
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = $this->tenant('Tenant A', 'tenant-a');
            $this->tenantB = $this->tenant('Tenant B', 'tenant-b');
            $this->branchA = $this->branch($this->tenantA, 'North', 'NORTH');
            $this->branchB = $this->branch($this->tenantA, 'South', 'SOUTH');
            $this->branchOther = $this->branch($this->tenantB, 'Other', 'OTHER');
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-a@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-a@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-a@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-a@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-a@example.com');
            $this->managerA->branches()->sync([$this->branchA->id]);
            $this->supervisorA->branches()->sync([$this->branchA->id]);
            $this->gatekeeperA->branches()->sync([$this->branchA->id]);
            $this->accountantA->branches()->sync([$this->branchA->id]);
        });
    }

    public function test_super_admin_can_access_every_tenant_and_branch(): void
    {
        $this->actingAs($this->superAdmin)->get('/tenants')->assertOk()->assertSee('Tenant A')->assertSee('Tenant B');
        $this->actingAs($this->superAdmin)->get('/branches')->assertOk()->assertSee('North')->assertSee('South')->assertSee('Other');
        $this->actingAs($this->superAdmin)->get('/branches/'.$this->branchOther->id)->assertOk();
        $this->actingAs($this->superAdmin)->get('/approval-limits')->assertOk()->assertSee('Expense');

        $this->actingAs($this->superAdmin)->post('/branches', [
            'name' => 'Unscoped',
            'code' => 'UNSCOPED',
            'tenant_id' => $this->tenantA->id,
        ])->assertForbidden();

        $this->actingAs($this->superAdmin)->post('/tenants/'.$this->tenantA->id.'/branches', [
            'name' => 'Routed',
            'code' => 'ROUTED',
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchOther->id,
        ])->assertRedirect();

        $routed = Branch::query()->where('code', 'ROUTED')->first();
        $this->assertNotNull($routed);
        $this->assertSame($this->tenantA->id, $routed->tenant_id);

        $this->actingAs($this->adminA)
            ->post('/tenants/'.$this->tenantB->id.'/branches', [
                'name' => 'Cross',
                'code' => 'CROSS',
            ])->assertNotFound();
    }

    public function test_tenant_roles_cannot_open_another_tenant_branch(): void
    {
        foreach ([$this->adminA, $this->managerA, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
            $this->actingAs($user)->get('/tenants/'.$this->tenantB->id)->assertNotFound();
            $this->actingAs($user)->get('/branches/'.$this->branchOther->id)->assertNotFound();
        }
    }

    public function test_assigned_branch_access_and_ignored_ids(): void
    {
        $this->actingAs($this->managerA)->get('/branches/'.$this->branchA->id)->assertOk();
        $this->actingAs($this->managerA)->get('/branches/'.$this->branchB->id)->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/branches/'.$this->branchB->id)->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/branches/'.$this->branchB->id)->assertForbidden();
        $this->actingAs($this->accountantA)->get('/branches/'.$this->branchB->id)->assertForbidden();

        $this->actingAs($this->adminA)->post('/branches', [
            'name' => 'West',
            'code' => 'WEST',
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchOther->id,
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches', [
            'name' => 'West',
            'code' => 'WEST',
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->branchOther->id,
        ])->assertRedirect();

        $created = Branch::query()->where('code', 'WEST')->first();
        $this->assertNotNull($created);
        $this->assertSame($this->tenantA->id, $created->tenant_id);

        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/assignments', [
            'user_id' => $this->managerA->id,
            'branch_id' => $this->branchB->id,
            'tenant_id' => $this->tenantB->id,
        ])->assertRedirect();

        $this->assertTrue($this->managerA->fresh()->branches->contains($this->branchA));
        $this->assertFalse($this->managerA->fresh()->branches->contains($this->branchB));
    }

    public function test_admin_cannot_modify_super_admin_or_own_role(): void
    {
        $this->actingAs($this->adminA)
            ->put('/users/'.$this->superAdmin->id.'/role', ['role' => RoleName::Admin->value])
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->put('/users/'.$this->adminA->id.'/role', ['role' => RoleName::Manager->value])
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->put('/users/'.$this->managerA->id.'/role', ['role' => RoleName::SuperAdmin->value])
            ->assertSessionHasErrors('role');

        $this->expectException(ValidationException::class);
        app(AssignUserRole::class)->assign($this->adminA, $this->adminA, RoleName::Manager);
    }

    public function test_financial_fields_and_actions_are_hidden_from_floor_roles(): void
    {
        $fields = app(FieldAccess::class);

        $this->assertTrue($fields->denies($this->gatekeeperA, 'rate'));
        $this->assertTrue($fields->denies($this->gatekeeperA, 'balance'));
        $this->assertTrue($fields->denies($this->gatekeeperA, 'invoice_amount'));
        $this->assertTrue($fields->denies($this->gatekeeperA, 'ledger'));
        $this->assertArrayNotHasKey('balance', $fields->filter($this->gatekeeperA, ['name' => 'Ali', 'balance' => '10']));

        $this->assertTrue($fields->denies($this->supervisorA, 'invoice_amount'));
        $this->assertTrue($fields->denies($this->supervisorA, 'ledger'));
        $this->assertFalse($fields->denies($this->supervisorA, 'rate'));

        $this->assertFalse($this->gatekeeperA->hasPermission('bookings.view'));
        $this->assertFalse($this->gatekeeperA->hasPermission('billing.view'));
        $this->assertFalse($this->gatekeeperA->hasPermission('payments.view'));
        $this->assertFalse($this->supervisorA->hasPermission('billing.view'));
        $this->assertFalse($this->supervisorA->hasPermission('payments.view'));

        $this->actingAs($this->gatekeeperA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/invoices')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/invoices')->assertOk();
    }

    public function test_security_foundations(): void
    {
        $maker = app(MakerChecker::class);
        $maker->assertCanApprove($this->managerA, $this->supervisorA->id);

        try {
            $maker->assertCanApprove($this->managerA, $this->managerA->id);
            $this->fail('Maker approved their own record.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        app(ReasonRequirement::class)->assert('edit_after_approval', 'Corrected weight');

        try {
            app(ReasonRequirement::class)->assert('delete', '');
            $this->fail('Deletion without a reason was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertTrue(app(RestoreAuthorization::class)->allows($this->superAdmin));
        $this->assertFalse(app(RestoreAuthorization::class)->allows($this->adminA));

        app(FinancialDocumentGuard::class)->assertMutable('draft');

        try {
            app(FinancialDocumentGuard::class)->assertMutable('posted');
            $this->fail('A posted document was treated as editable.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $window = app(ShiftEditWindow::class);
        $window->assertCanEdit($this->gatekeeperA, $this->gatekeeperA->id, now());

        try {
            $window->assertCanEdit($this->supervisorA, $this->supervisorA->id, now()->subDay());
            $this->fail('A next-day edit was allowed without manager approval.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $window->assertCanEdit($this->supervisorA, $this->supervisorA->id, now()->subDay(), $this->supervisorA);
            $this->fail('The same user was accepted as the approver.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $window->assertCanEdit($this->supervisorA, $this->supervisorA->id, now()->subDay(), $this->managerA);

        $this->assertTrue(app(ApprovalLimitEvaluator::class)->withinManagerLimit('expense', 100));
        $this->assertFalse(app(ApprovalLimitEvaluator::class)->withinManagerLimit('expense', 9000));

        app(TenantContext::class)->resolveForTenant($this->tenantA->id);
        $this->actingAs($this->adminA);

        $log = app(AuditLogger::class)->record('security.checked', null, ['ok' => true], $this->tenantA->id, [
            'module' => 'security',
            'branch_id' => $this->branchA->id,
            'reason' => 'Foundation check',
            'old' => ['status' => 'draft'],
            'new' => ['status' => 'checked'],
        ]);

        $this->assertSame('Foundation check', $log->reason);
        $this->assertSame($this->branchA->id, $log->branch_id);
        $this->assertNotNull($log->ip_address);

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Audit log update was allowed.');
        } catch (ImmutableRecordException) {
            $this->assertSame('security.checked', $log->fresh()->action);
        }

        try {
            $log->delete();
            $this->fail('Audit log delete was allowed.');
        } catch (ImmutableRecordException) {
            $this->assertNotNull(AuditLog::query()->find($log->id));
        }

        try {
            AuditLog::query()->whereKey($log->id)->update(['action' => 'tampered']);
            $this->fail('A query update changed an audit log.');
        } catch (ImmutableRecordException) {
            $this->assertSame('security.checked', AuditLog::query()->find($log->id)->action);
        }

        try {
            AuditLog::query()->whereKey($log->id)->delete();
            $this->fail('A query delete removed an audit log.');
        } catch (ImmutableRecordException) {
            $this->assertNotNull(AuditLog::query()->find($log->id));
        }
    }

    public function test_legacy_roles_are_remapped_without_deleting_users(): void
    {
        $viewer = app(TenantContext::class)->bypass(function (): User {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Viewer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = User::query()->create([
                'tenant_id' => $this->tenantA->id,
                'name' => 'Legacy Viewer',
                'email' => 'legacy-viewer@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);
            $user->roles()->attach($roleId);

            return $user;
        });

        (new RemapLegacyRoles)->run();

        $this->assertTrue($viewer->fresh()->hasRole(RoleName::Supervisor));
        $this->assertNull(Role::query()->where('name', 'Viewer')->first());
        $this->assertNotNull(Role::withTrashed()->where('name', 'Viewer')->first());
    }

    private function tenant(string $name, string $slug): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'status' => TenantStatus::Active,
        ]);
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
