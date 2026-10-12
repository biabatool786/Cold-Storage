<?php

namespace Tests\Feature\Maintenance;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\AssetContract;
use App\Models\Csms\BreakdownTicket;
use App\Models\Csms\FacilityAsset;
use App\Models\Csms\MaintenanceWorkOrder;
use App\Models\Csms\PmSchedule;
use App\Models\Csms\SparePart;
use App\Models\Csms\SparePartMovement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\Navigation;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
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
                'name' => 'Maint Co', 'slug' => 'maint-co', 'email' => 'maint@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'South', 'code' => 'SOUTH', 'status' => BranchStatus::Active,
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

    public function test_asset_create_edit_permissions_and_denials(): void
    {
        $this->actingAs($this->gatekeeperA)->get('/assets')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/assets/create')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/assets')->assertOk()->assertSee('Cost')->assertDontSee('Add asset');

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/assets', [
            'asset_code' => 'CMP-01',
            'name' => 'Compressor 1',
            'purchase_cost' => 12000,
            'operational_status' => 'active',
            'tenant_id' => 9999,
            'branch_id' => $this->branchB->id,
        ])->assertRedirect();

        $asset = FacilityAsset::query()->where('asset_code', 'CMP-01')->firstOrFail();
        $this->assertSame($this->tenantA->id, (int) $asset->tenant_id);
        $this->assertSame($this->branchA->id, (int) $asset->branch_id);

        $this->actingAs($this->managerA)->put('/assets/'.$asset->id, [
            'asset_code' => 'CMP-01',
            'name' => 'Compressor 1B',
            'purchase_cost' => 12500,
        ])->assertRedirect();
        $this->assertSame('Compressor 1B', $asset->fresh()->name);

        $this->actingAs($this->supervisorA)->put('/assets/'.$asset->id, [
            'asset_code' => 'CMP-01', 'name' => 'Hacked',
        ])->assertForbidden();

        $this->assertTrue(AuditLog::query()->where('action', 'maintenance.asset_created')->exists());
    }

    public function test_tenant_isolation_and_branch_spoofing(): void
    {
        $asset = $this->asset('ISO-1');
        $foreign = null;
        app(TenantContext::class)->bypass(function () use (&$foreign): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other', 'slug' => 'other-m', 'email' => 'om@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchX = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'X', 'code' => 'X1', 'status' => BranchStatus::Active,
            ]);
            $foreign = $this->user($tenantB, RoleName::Manager, 'mgr-b@example.com');
            $foreign->branches()->sync([$branchX->id]);
        });

        $this->actingAs($foreign)->get('/assets/'.$asset->id)->assertNotFound();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/assets', [
            'asset_code' => 'NOPE', 'name' => 'Nope',
        ])->assertForbidden();
    }

    public function test_accountant_cost_only_and_spare_view(): void
    {
        $asset = $this->asset('COST-1', ['purchase_cost' => 5000, 'serial_number' => 'SN-SECRET', 'location' => 'Plant room']);
        $this->actingAs($this->accountantA)->get('/assets/'.$asset->id)
            ->assertOk()
            ->assertSee('5,000.00')
            ->assertDontSee('SN-SECRET')
            ->assertDontSee('Plant room');

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/maintenance/spares', [
            'part_code' => 'FLT-1', 'name' => 'Filter', 'unit' => 'pcs', 'min_stock' => 2,
        ])->assertRedirect();
        $this->actingAs($this->accountantA)->get('/maintenance/spares')->assertOk()->assertSee('FLT-1');
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/maintenance/spares', [
            'part_code' => 'X', 'name' => 'X',
        ])->assertForbidden();
    }

    public function test_pm_schedule_permissions_and_due_status(): void
    {
        $asset = $this->asset('PM-1');
        $this->actingAs($this->supervisorA)->post('/maintenance/schedules', [
            'facility_asset_id' => $asset->id,
            'maintenance_type' => 'oil',
            'frequency_days' => 30,
            'next_due_on' => now()->subDay()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/maintenance/schedules', [
            'facility_asset_id' => $asset->id,
            'maintenance_type' => 'oil',
            'frequency_days' => 30,
            'next_due_on' => now()->subDay()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($this->managerA)->get('/maintenance/schedules')->assertOk()->assertSee('overdue');
        $schedule = PmSchedule::query()->firstOrFail();
        $this->assertSame('overdue', $schedule->fresh()->status);
    }

    public function test_gatekeeper_report_only(): void
    {
        $asset = $this->asset('GK-1');
        $this->actingAs($this->gatekeeperA)->post('/maintenance/tickets', [
            'facility_asset_id' => $asset->id,
            'description' => 'Loud noise',
            'priority' => 'high',
        ])->assertRedirect();
        $ticket = BreakdownTicket::query()->firstOrFail();

        $this->actingAs($this->gatekeeperA)->post('/maintenance/tickets/'.$ticket->id.'/assign', [
            'assigned_user_id' => $this->managerA->id,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'description' => 'Fix it',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/assets')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/maintenance/tickets')->assertOk();
    }

    public function test_supervisor_work_order_request_without_approve_or_close(): void
    {
        $asset = $this->asset('SV-1');
        $this->actingAs($this->supervisorA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'description' => 'Need repair',
            'estimated_cost' => 800,
        ])->assertRedirect();
        $wo = MaintenanceWorkOrder::query()->firstOrFail();
        $this->assertSame('request', $wo->kind);
        $this->assertSame('requested', $wo->status);

        $this->actingAs($this->supervisorA)->post('/maintenance/work-orders/'.$wo->id.'/decide-cost', [
            'approve' => 1,
        ])->assertForbidden();

        $ticket = $this->ticket($asset);
        $this->actingAs($this->supervisorA)->post('/maintenance/tickets/'.$ticket->id.'/close', [
            'closure_notes' => 'done',
        ])->assertForbidden();
    }

    public function test_work_order_cost_approval_limits_and_self_approval(): void
    {
        $asset = $this->asset('WO-1');
        $this->actingAs($this->managerA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'description' => 'Motor rewind',
            'estimated_cost' => 2000,
        ])->assertRedirect();
        $wo = MaintenanceWorkOrder::query()->where('estimated_cost', 2000)->firstOrFail();

        $this->actingAs($this->managerA)->post('/maintenance/work-orders/'.$wo->id.'/decide-cost', ['approve' => 1])
            ->assertForbidden();
        $this->actingAs($this->adminA)->post('/maintenance/work-orders/'.$wo->id.'/decide-cost', ['approve' => 1])
            ->assertForbidden();
        $this->actingAs($this->manager2)->post('/maintenance/work-orders/'.$wo->id.'/decide-cost', ['approve' => 1])
            ->assertRedirect();
        $this->assertSame('approved', $wo->fresh()->cost_status);

        $this->actingAs($this->managerA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'description' => 'Big job',
            'estimated_cost' => 9000,
        ])->assertRedirect();
        $big = MaintenanceWorkOrder::query()->where('estimated_cost', 9000)->firstOrFail();
        $this->actingAs($this->manager2)->post('/maintenance/work-orders/'.$big->id.'/decide-cost', ['approve' => 1])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/maintenance/work-orders/'.$big->id.'/decide-cost', ['approve' => 1])
            ->assertRedirect();
    }

    public function test_assignment_and_work_status_parts_log(): void
    {
        $asset = $this->asset('AS-1');
        $ticket = $this->ticket($asset);
        $this->actingAs($this->gatekeeperA)->post('/maintenance/tickets/'.$ticket->id.'/assign', [
            'assigned_user_id' => $this->supervisorA->id,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/maintenance/tickets/'.$ticket->id.'/assign', [
            'assigned_user_id' => $this->supervisorA->id,
        ])->assertRedirect();

        $this->actingAs($this->managerA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'breakdown_ticket_id' => $ticket->id,
            'description' => 'Repair',
            'estimated_cost' => 100,
        ])->assertRedirect();
        $wo = MaintenanceWorkOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/maintenance/spares', [
            'part_code' => 'BELT', 'name' => 'Belt',
        ])->assertRedirect();
        $part = SparePart::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/maintenance/spares/'.$part->id.'/move', [
            'movement_type' => 'receipt', 'quantity' => 5,
        ])->assertRedirect();

        $this->actingAs($this->supervisorA)->post('/maintenance/work-orders/'.$wo->id.'/status', [
            'status' => 'in_progress', 'message' => 'Started',
        ])->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/maintenance/work-orders/'.$wo->id.'/issue-part', [
            'spare_part_id' => $part->id, 'quantity' => 2,
        ])->assertRedirect();
        $this->assertSame(3.0, (float) $part->fresh()->quantity_on_hand);
    }

    public function test_spare_stock_accuracy_and_negative_prevention(): void
    {
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/maintenance/spares', [
            'part_code' => 'SEAL', 'name' => 'Seal',
        ])->assertRedirect();
        $part = SparePart::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/maintenance/spares/'.$part->id.'/move', [
            'movement_type' => 'receipt', 'quantity' => 3,
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/maintenance/spares/'.$part->id.'/move', [
            'movement_type' => 'issue', 'quantity' => 10,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(3.0, (float) $part->fresh()->quantity_on_hand);
        $this->assertSame(1, SparePartMovement::query()->where('kind', 'receipt')->count());
        $this->assertSame(0, SparePartMovement::query()->where('kind', 'issue')->count());
    }

    public function test_amc_warranty_access_and_expiry(): void
    {
        $asset = $this->asset('AMC-1');
        $this->actingAs($this->gatekeeperA)->get('/maintenance/contracts')->assertForbidden();
        $this->actingAs($this->managerA)->post('/maintenance/contracts', [
            'facility_asset_id' => $asset->id,
            'kind' => 'amc',
            'provider_name' => 'CoolCare',
            'starts_on' => now()->subMonths(11)->toDateString(),
            'ends_on' => now()->addDays(10)->toDateString(),
            'cost' => 1500,
        ])->assertRedirect();
        $contract = AssetContract::query()->firstOrFail();
        $this->assertSame('expiring', $contract->expiryLabel());
        $this->actingAs($this->supervisorA)->get('/maintenance/contracts')->assertOk()->assertSee('CoolCare');
        $this->actingAs($this->accountantA)->get('/maintenance/contracts')->assertOk()->assertSee('1,500.00');
    }

    public function test_downtime_cost_uses_recorded_data(): void
    {
        $asset = $this->asset('DT-1');
        $ticket = $this->ticket($asset);
        $ticket->update([
            'reported_at' => now()->subHours(5),
            'closed_at' => now(),
            'status' => 'closed',
        ]);
        $this->actingAs($this->managerA)->post('/maintenance/work-orders', [
            'facility_asset_id' => $asset->id,
            'description' => 'Fix',
            'estimated_cost' => 300,
        ])->assertRedirect();
        $wo = MaintenanceWorkOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->managerA)->post('/maintenance/work-orders/'.$wo->id.'/status', [
            'status' => 'completed', 'message' => 'Done', 'actual_cost' => 250,
        ])->assertRedirect();

        $this->actingAs($this->managerA)->get('/maintenance/history')
            ->assertOk()
            ->assertSee('250.00')
            ->assertDontSee('300.00');
    }

    public function test_qr_print_permissions_and_no_tenant_secrets(): void
    {
        $asset = $this->asset('QR-1');
        $this->actingAs($this->supervisorA)->get('/assets/'.$asset->id.'/qr')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/assets/'.$asset->id.'/qr')->assertForbidden();
        $this->actingAs($this->managerA)->get('/assets/'.$asset->id.'/qr')
            ->assertOk()
            ->assertSee('CSMS-ASSET:'.$asset->id.':QR-1')
            ->assertDontSee($this->tenantA->name)
            ->assertDontSee('tenant');
        $this->assertTrue(AuditLog::query()->where('action', 'maintenance.asset_qr_printed')->exists());
    }

    public function test_unauthorized_url_access_and_navigation_visibility(): void
    {
        $asset = $this->asset('NAV-1');
        $this->actingAs($this->gatekeeperA)->get('/maintenance/spares')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/maintenance/history')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/assets/'.$asset->id.'/edit')->assertForbidden();

        $gk = collect(Navigation::modules($this->gatekeeperA))->firstWhere('key', 'equipment');
        $this->assertNotNull($gk);
        $gkHref = $gk['href'] ?? ($gk['items'][0]['href'] ?? '');
        $this->assertStringContainsString('/maintenance/tickets', $gkHref);

        $acct = collect(Navigation::modules($this->accountantA))->firstWhere('key', 'equipment');
        $acctLabels = collect($acct['type'] === 'group' ? $acct['items'] : [['label' => $acct['label']]])->pluck('label');
        $this->assertTrue($acctLabels->contains('Assets'));
        $this->assertFalse($acctLabels->contains('Work orders'));
        $this->assertFalse($acctLabels->contains('Breakdown tickets'));

        $mgr = collect(Navigation::modules($this->managerA))->firstWhere('key', 'equipment');
        $this->assertNotNull($mgr);
        $this->assertSame('group', $mgr['type']);
    }

    private function asset(string $code, array $extra = []): FacilityAsset
    {
        return app(TenantContext::class)->bypass(fn () => FacilityAsset::query()->create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'asset_code' => $code,
            'name' => $code,
            'title' => $code,
            'operational_status' => 'active',
            'status' => 'open',
            'created_by' => $this->managerA->id,
        ], $extra)));
    }

    private function ticket(FacilityAsset $asset): BreakdownTicket
    {
        return app(TenantContext::class)->bypass(fn () => BreakdownTicket::query()->create([
            'tenant_id' => $asset->tenant_id,
            'branch_id' => $asset->branch_id,
            'facility_asset_id' => $asset->id,
            'number' => 'BK-TEST-'.uniqid(),
            'description' => 'Broken',
            'priority' => 'medium',
            'reported_at' => now(),
            'status' => 'open',
            'reported_by' => $this->gatekeeperA->id,
        ]));
    }

    private function user(?Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'password' => 'password',
            'email' => $email,
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}
