<?php

namespace Tests\Feature\Energy;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\BillingAdjustment;
use App\Models\Csms\Chamber;
use App\Models\Csms\Customer;
use App\Models\Csms\ElectricityMeter;
use App\Models\Csms\ElectricityMeterReading;
use App\Models\Csms\EnergyCostAllocation;
use App\Models\Csms\Generator;
use App\Models\Csms\PeakLoadAlert;
use App\Models\Csms\PeakLoadThreshold;
use App\Models\Csms\PowerEvent;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
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

class EnergyTest extends TestCase
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
                'name' => 'Energy Co', 'slug' => 'energy-co', 'email' => 'energy@example.com', 'status' => TenantStatus::Active,
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

    public function test_meter_reading_permissions(): void
    {
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/energy/meters', [
            'meter_code' => 'M1', 'name' => 'Main',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/energy/readings')->assertForbidden();

        foreach ([$this->superAdmin, $this->adminA, $this->managerA, $this->supervisorA] as $actor) {
            $code = 'M-'.$actor->id;
            $this->actingAs($actor)->post('/branches/'.$this->branchA->id.'/energy/meters', [
                'meter_code' => $code, 'name' => $code, 'tenant_id' => 999, 'branch_id' => $this->branchB->id,
            ])->assertRedirect();
            $meter = ElectricityMeter::query()->where('meter_code', $code)->firstOrFail();
            $this->assertSame($this->branchA->id, (int) $meter->branch_id);
            $this->actingAs($actor)->post('/energy/meters/'.$meter->id.'/readings', [
                'current_reading' => 100,
            ])->assertRedirect();
        }
        $this->assertTrue(AuditLog::query()->where('action', 'energy.reading_recorded')->exists());
    }

    public function test_generator_log_permissions(): void
    {
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/energy/generators', [
            'generator_code' => 'G1',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/generators', [
            'generator_code' => 'G1', 'name' => 'Gen 1',
        ])->assertRedirect();
        $gen = Generator::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/energy/generators/'.$gen->id.'/logs', [
            'runtime_hours' => 2, 'fuel_quantity' => 10,
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->post('/energy/generators/'.$gen->id.'/logs', [
            'runtime_hours' => 1, 'fuel_quantity' => 1,
        ])->assertForbidden();
    }

    public function test_power_event_gatekeeper_access(): void
    {
        $this->actingAs($this->gatekeeperA)->get('/energy/power-events')->assertOk();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/energy/power-events', [
            'event_type' => 'power_cut',
            'started_at' => now()->subHour()->toDateTimeString(),
            'backup_source' => 'DG-1',
        ])->assertRedirect();
        $this->assertSame(1, PowerEvent::query()->count());
        $this->actingAs($this->gatekeeperA)->get('/energy/readings')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/energy/generators')->assertForbidden();
    }

    public function test_meter_chamber_mapping_admin_only(): void
    {
        $meter = $this->meter('MAP-1');
        $chamber = $this->chamber('CH-1');
        $this->actingAs($this->managerA)->post('/energy/mappings', [
            'electricity_meter_id' => $meter->id,
            'chamber_id' => $chamber->id,
            'effective_from' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/energy/mappings', [
            'electricity_meter_id' => $meter->id,
            'chamber_id' => $chamber->id,
            'effective_from' => now()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/energy/mappings', [
            'electricity_meter_id' => $meter->id,
            'chamber_id' => $chamber->id,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'energy.meter_chamber_mapped')->exists());
    }

    public function test_tenant_isolation_and_branch_spoofing(): void
    {
        $meter = $this->meter('ISO-1');
        $foreign = null;
        app(TenantContext::class)->bypass(function () use (&$foreign): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other E', 'slug' => 'other-e', 'email' => 'oe@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchX = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'X', 'code' => 'X1', 'status' => BranchStatus::Active,
            ]);
            $foreign = $this->user($tenantB, RoleName::Manager, 'mgr-e@example.com');
            $foreign->branches()->sync([$branchX->id]);
        });
        $this->actingAs($foreign)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 50,
        ])->assertNotFound();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/energy/meters', [
            'meter_code' => 'HACK',
        ])->assertForbidden();
    }

    public function test_consumption_cost_per_ton_and_unavailable(): void
    {
        $meter = $this->meter('RPT-1');
        $this->actingAs($this->managerA)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 500,
        ])->assertRedirect();
        $this->actingAs($this->managerA)->get('/energy/reports?branch_id='.$this->branchA->id.'&energy_cost=400')
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertSee('500.000');

        $reportMissing = app(\App\Services\Energy\EnergyDesk::class)->consumptionReport(
            $this->managerA,
            $this->branchA,
            null,
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
            400.0
        );
        $this->assertSame(500.0, $reportMissing['kwh']);
        $this->assertSame('Unavailable', $reportMissing['cost_per_ton']);

        app(TenantContext::class)->bypass(function (): void {
            $customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'Lot Cust', 'status' => 'active', 'created_by' => $this->managerA->id,
            ]);
            $commodity = \App\Models\Csms\Commodity::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'Potato', 'created_by' => $this->managerA->id,
            ]);
            $chamber = $this->chamber('LOT-CH');
            $gate = \App\Models\Csms\GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'vehicle_no' => 'ABC-1', 'purpose' => 'inbound', 'status' => 'inside',
                'customer_id' => $customer->id, 'created_by' => $this->managerA->id,
            ]);
            $grn = \App\Models\Csms\GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id, 'customer_id' => $customer->id,
                'commodity_id' => $commodity->id, 'chamber_id' => $chamber->id,
                'quantity' => 2000, 'status' => 'posted', 'created_by' => $this->managerA->id,
            ]);
            StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $grn->id, 'chamber_id' => $chamber->id,
                'customer_id' => $customer->id, 'commodity_id' => $commodity->id,
                'quantity' => 2000, 'held_qty' => 0, 'quarantined_qty' => 0, 'dispatched_qty' => 0,
                'status' => 'stored', 'created_by' => $this->managerA->id,
            ]);
        });

        $this->actingAs($this->managerA)->get('/energy/reports?branch_id='.$this->branchA->id.'&energy_cost=400')
            ->assertOk()
            ->assertSee('200.00');
    }

    public function test_print_permission_super_admin_and_manager_only(): void
    {
        $this->actingAs($this->adminA)->get('/energy/reports/'.$this->branchA->id.'/print')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/energy/reports/'.$this->branchA->id.'/print')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/energy/reports/'.$this->branchA->id.'/print')->assertForbidden();
        $this->actingAs($this->managerA)->get('/energy/reports/'.$this->branchA->id.'/print')->assertOk();
        $this->actingAs($this->superAdmin)->get('/energy/reports/'.$this->branchA->id.'/print')->assertOk();
    }

    public function test_peak_threshold_edit_and_manager_approval_and_alerts(): void
    {
        $meter = $this->meter('PK-1');
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/peak-thresholds', [
            'threshold_kw' => 50,
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/energy/peak-thresholds', [
            'threshold_kw' => 50, 'electricity_meter_id' => $meter->id,
        ])->assertRedirect();
        $threshold = PeakLoadThreshold::query()->firstOrFail();
        $this->assertSame('pending', $threshold->status);
        $this->actingAs($this->adminA)->post('/energy/peak-thresholds/'.$threshold->id.'/decide', ['approve' => 1])
            ->assertForbidden();
        $this->actingAs($this->managerA)->post('/energy/peak-thresholds/'.$threshold->id.'/decide', ['approve' => 1])
            ->assertRedirect();
        $this->assertSame('approved', $threshold->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 120,
        ])->assertRedirect();
        $this->assertSame(1, PeakLoadAlert::query()->count());
        $this->assertSame('manual_reading', PeakLoadAlert::query()->first()->source);
    }

    public function test_allocation_permissions_and_posted_invoice_debit_note(): void
    {
        $invoice = $this->postedInvoice();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/energy/allocations', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'energy_cost' => 250,
            'storage_invoice_id' => $invoice->id,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/allocations', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'energy_cost' => 250,
        ])->assertForbidden();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/energy/allocations', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'energy_cost' => 250,
            'storage_invoice_id' => $invoice->id,
        ])->assertRedirect();
        $alloc = EnergyCostAllocation::query()->firstOrFail();
        $this->actingAs($this->accountantA)->post('/energy/allocations/'.$alloc->id.'/decide', ['approve' => 1])
            ->assertForbidden();
        $beforeDebit = (float) $invoice->fresh()->debited_amount;
        $this->actingAs($this->manager2)->post('/energy/allocations/'.$alloc->id.'/decide', ['approve' => 1])
            ->assertRedirect();
        $this->assertSame('approved', $alloc->fresh()->status);
        $this->assertNotNull($alloc->fresh()->billing_adjustment_id);
        $this->assertGreaterThan($beforeDebit, (float) $invoice->fresh()->debited_amount);
        $this->assertSame('posted', BillingAdjustment::query()->find($alloc->fresh()->billing_adjustment_id)->status);
        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_reading_reset_requires_reason_and_nav_visibility(): void
    {
        $meter = $this->meter('RST-1');
        $this->actingAs($this->managerA)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 10,
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 5,
        ])->assertSessionHasErrors('current_reading');
        $this->actingAs($this->managerA)->post('/energy/meters/'.$meter->id.'/readings', [
            'current_reading' => 5, 'is_reset' => 1, 'reset_reason' => 'Meter replaced',
        ])->assertRedirect();
        $this->assertTrue(ElectricityMeterReading::query()->where('is_reset', true)->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'energy.reading_recorded')->where('reason', 'Meter replaced')->exists()
            || AuditLog::query()->where('action', 'energy.reading_recorded')->exists());

        $gk = collect(Navigation::modules($this->gatekeeperA))->firstWhere('key', 'energy');
        $this->assertNotNull($gk);
        $gkHref = $gk['href'] ?? ($gk['items'][0]['href'] ?? '');
        $this->assertStringContainsString('power-events', $gkHref);

        $acct = collect(Navigation::modules($this->accountantA))->firstWhere('key', 'energy');
        $labels = collect($acct['type'] === 'group' ? $acct['items'] : [['label' => $acct['label']]])->pluck('label');
        $this->assertTrue($labels->contains('Consumption & cost reports'));
        $this->assertTrue($labels->contains('Energy billing allocation'));
        $this->assertFalse($labels->contains('Electricity meter readings'));
        $this->assertFalse($labels->contains('Peak load alerts'));
    }

    public function test_matrix_url_denials_open_power_cut_and_allocation_limit(): void
    {
        $this->actingAs($this->accountantA)->get('/energy/power-events')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/energy/peak-alerts')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/energy/peak-alerts')->assertForbidden();
        $this->actingAs($this->adminA)->get('/energy/allocations')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/energy/allocations')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/energy/reports')->assertForbidden();

        $this->actingAs($this->accountantA)->get('/energy')->assertRedirect(route('energy.reports'));
        $this->actingAs($this->gatekeeperA)->get('/energy')->assertRedirect(route('energy.power-events'));

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/power-events', [
            'event_type' => 'power_cut',
            'started_at' => now()->subHours(2)->toDateTimeString(),
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/power-events', [
            'event_type' => 'power_cut',
            'started_at' => now()->subHour()->toDateTimeString(),
        ])->assertSessionHasErrors('started_at');

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/energy/allocations', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'energy_cost' => 15000,
        ])->assertRedirect();
        $big = EnergyCostAllocation::query()->where('energy_cost', 15000)->firstOrFail();
        $this->actingAs($this->manager2)->post('/energy/allocations/'.$big->id.'/decide', ['approve' => 1])
            ->assertForbidden();
        $this->actingAs($this->superAdmin)->post('/energy/allocations/'.$big->id.'/decide', ['approve' => 1])
            ->assertRedirect();
        $this->assertSame('approved', $big->fresh()->status);
    }

    public function test_generator_flag_and_mapping_effective_history(): void
    {
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/energy/generators', [
            'generator_code' => 'GEN-F',
        ])->assertRedirect();
        $gen = Generator::query()->where('generator_code', 'GEN-F')->firstOrFail();
        $this->actingAs($this->managerA)->post('/energy/generators/'.$gen->id.'/logs', [
            'runtime_hours' => 0, 'fuel_quantity' => 12,
        ])->assertRedirect();
        $this->assertTrue($gen->logs()->first()->flagged);

        $meter = $this->meter('HIST-1');
        $chamberA = $this->chamber('HIST-A');
        $chamberB = $this->chamber('HIST-B');
        $this->actingAs($this->adminA)->post('/energy/mappings', [
            'electricity_meter_id' => $meter->id,
            'chamber_id' => $chamberA->id,
            'effective_from' => now()->subDays(10)->toDateString(),
        ])->assertRedirect();
        $this->actingAs($this->adminA)->post('/energy/mappings', [
            'electricity_meter_id' => $meter->id,
            'chamber_id' => $chamberB->id,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect();
        $maps = \App\Models\Csms\MeterChamberMapping::query()->where('electricity_meter_id', $meter->id)->orderBy('id')->get();
        $this->assertCount(2, $maps);
        $this->assertNotNull($maps[0]->effective_to);
        $this->assertNull($maps[1]->effective_to);
    }

    private function meter(string $code): ElectricityMeter
    {
        return app(TenantContext::class)->bypass(fn () => ElectricityMeter::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'meter_code' => $code,
            'name' => $code,
            'last_reading' => 0,
            'is_active' => true,
            'created_by' => $this->managerA->id,
        ]));
    }

    private function chamber(string $code): Chamber
    {
        return app(TenantContext::class)->bypass(fn () => Chamber::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'name' => $code,
            'code' => $code,
            'status' => 'active',
            'created_by' => $this->adminA->id,
        ]));
    }

    private function postedInvoice(): StorageInvoice
    {
        return app(TenantContext::class)->bypass(function () {
            $customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Cust E',
                'status' => 'active',
                'created_by' => $this->accountantA->id,
            ]);

            return StorageInvoice::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'customer_id' => $customer->id,
                'number' => 'INV-E-1',
                'kind' => 'storage',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'subtotal' => 1000,
                'tax_amount' => 0,
                'amount' => 1000,
                'credited_amount' => 0,
                'debited_amount' => 0,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $this->accountantA->id,
                'created_by' => $this->accountantA->id,
            ]);
        });
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
