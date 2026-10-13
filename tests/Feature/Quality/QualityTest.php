<?php

namespace Tests\Feature\Quality;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\QualityCertificate;
use App\Models\Csms\QualityChecklistTemplate;
use App\Models\Csms\QualityIncident;
use App\Models\Csms\QualityInspection;
use App\Models\Csms\QualityInsuranceClaim;
use App\Models\Csms\QualityInsurancePolicy;
use App\Models\Csms\QualityNcr;
use App\Models\Csms\QualityPestSanitationLog;
use App\Models\Csms\QualitySafetyLog;
use App\Models\Csms\QualitySopDocument;
use App\Models\Csms\QualitySopVersion;
use App\Models\Csms\StockLedgerEntry;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QualityTest extends TestCase
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
                'name' => 'Quality Co', 'slug' => 'quality-co', 'email' => 'q@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'QNORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'South', 'code' => 'QSOUTH', 'status' => BranchStatus::Active,
            ]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super-q@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-q@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-q@example.com');
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2-q@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-q@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-q@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-q@example.com');
            foreach ([$this->managerA, $this->manager2, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
        });
    }

    public function test_template_create_edit_permissions(): void
    {
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/templates', [
            'name' => 'In', 'category' => 'incoming', 'items' => "A\nB",
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/quality/templates')->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/templates', [
            'name' => 'Incoming QC', 'category' => 'incoming', 'items' => "Temp check\n?Optional note",
            'tenant_id' => 999,
        ])->assertRedirect();
        $tpl = QualityChecklistTemplate::query()->firstOrFail();
        $this->assertSame(1, (int) $tpl->current_version);
        $this->actingAs($this->adminA)->post('/quality/templates/'.$tpl->id, [
            'name' => 'Incoming QC', 'category' => 'incoming', 'items' => "Temp check\nPackaging", 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame(2, (int) $tpl->fresh()->current_version);
        $this->assertTrue(AuditLog::query()->where('action', 'quality.template_versioned')->exists());
    }

    public function test_inspection_permissions_and_validation(): void
    {
        $tpl = $this->template();
        $this->actingAs($this->adminA)->get('/quality/inspections')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/quality/inspections', [
            'quality_checklist_template_id' => $tpl->id,
            'responses' => [['response' => 'pass'], ['response' => 'pass']],
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/inspections', [
            'quality_checklist_template_id' => $tpl->id,
            'responses' => [['response' => ''], ['response' => 'pass']],
        ])->assertSessionHasErrors();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/inspections', [
            'quality_checklist_template_id' => $tpl->id,
            'responses' => [['response' => 'pass'], ['response' => 'na']],
        ])->assertRedirect();
        $this->assertSame('pass', QualityInspection::query()->first()->overall_result);
    }

    public function test_failed_inspection_and_ncr_creation(): void
    {
        $tpl = $this->template();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/inspections', [
            'quality_checklist_template_id' => $tpl->id,
            'responses' => [['response' => 'fail'], ['response' => 'pass']],
        ])->assertRedirect();
        $insp = QualityInspection::query()->firstOrFail();
        $this->assertSame('fail', $insp->overall_result);
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/quality/ncrs', [
            'reference_number' => 'NCR-1',
            'quality_inspection_id' => $insp->id,
            'description' => 'Failed temp',
            'severity' => 'high',
        ])->assertRedirect();
        $this->assertSame(1, QualityNcr::query()->count());
    }

    public function test_ncr_corrective_closure_and_self_approval(): void
    {
        $ncr = $this->ncr();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/corrective', [
            'quality_ncr_id' => $ncr->id, 'description' => 'Fix cooler',
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/quality/corrective', [
            'quality_ncr_id' => $ncr->id, 'description' => 'Fix cooler',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/corrective', [
            'quality_ncr_id' => $ncr->id, 'description' => 'Fix cooler', 'due_date' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/quality/ncrs/'.$ncr->id.'/request-closure', [
            'closure_evidence' => 'Photos attached',
        ])->assertRedirect();
        $this->actingAs($this->managerA)->post('/quality/ncrs/'.$ncr->id.'/decide', ['approve' => true])->assertForbidden();
        $this->actingAs($this->manager2)->post('/quality/ncrs/'.$ncr->id.'/decide', ['approve' => true])->assertRedirect();
        $this->assertSame('closed', $ncr->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'quality.ncr_closed')->exists());
    }

    public function test_pest_sanitation_permissions(): void
    {
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/quality/pest', [
            'log_type' => 'pest_control',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/quality/pest')->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/pest', [
            'log_type' => 'sanitation', 'location' => 'Dock', 'follow_up_on' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $this->assertSame(1, QualityPestSanitationLog::query()->count());
    }

    public function test_lot_traceability_forward_backward_and_missing_links(): void
    {
        $lot = $this->lotWithHistory();
        $this->actingAs($this->gatekeeperA)->get('/quality/traceability')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/quality/traceability?storage_lot_id='.$lot->id)
            ->assertOk()
            ->assertSee('Backward')
            ->assertSee('Forward');
        $orphan = app(TenantContext::class)->bypass(function () {
            $customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'Orphan Cust', 'status' => 'active', 'created_by' => $this->managerA->id,
            ]);
            $commodity = \App\Models\Csms\Commodity::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'Orphan C', 'status' => 'active',
            ]);
            $chamber = \App\Models\Csms\Chamber::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'name' => 'Orphan Ch', 'code' => 'ORCH', 'status' => 'active',
            ]);
            $gate = \App\Models\Csms\GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'vehicle_no' => 'OR-1', 'purpose' => 'inbound', 'status' => 'inside',
                'customer_id' => $customer->id, 'created_by' => $this->managerA->id,
            ]);
            $grn = GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id, 'customer_id' => $customer->id,
                'commodity_id' => $commodity->id, 'chamber_id' => $chamber->id,
                'number' => 'GRN-ORPH', 'quantity' => 1, 'status' => 'posted',
                'created_by' => $this->managerA->id,
            ]);
            $lot = StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $grn->id, 'chamber_id' => $chamber->id,
                'customer_id' => $customer->id, 'commodity_id' => $commodity->id,
                'lot_number' => 'ORPHAN-1', 'quantity' => 1, 'status' => 'stored',
            ]);
            $grn->delete();

            return $lot;
        });
        $this->actingAs($this->managerA)->get('/quality/traceability?storage_lot_id='.$orphan->id)
            ->assertOk()
            ->assertSee('Goods receipt / source receiving record unavailable');
    }

    public function test_traceability_print_super_admin_and_manager_only(): void
    {
        $lot = $this->lotWithHistory();
        $this->actingAs($this->supervisorA)->get('/quality/traceability/print?storage_lot_id='.$lot->id)->assertForbidden();
        $this->actingAs($this->adminA)->get('/quality/traceability/print?storage_lot_id='.$lot->id)->assertForbidden();
        $this->actingAs($this->accountantA)->get('/quality/traceability/print?storage_lot_id='.$lot->id)->assertForbidden();
        $this->actingAs($this->managerA)->get('/quality/traceability/print?storage_lot_id='.$lot->id)->assertOk();
        $this->actingAs($this->superAdmin)->get('/quality/traceability/print?storage_lot_id='.$lot->id)->assertOk();
    }

    public function test_certificate_permissions_and_expiry_status(): void
    {
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/certificates', [
            'document_type' => 'License', 'reference_number' => 'L1',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/quality/certificates')->assertOk();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/certificates', [
            'document_type' => 'License',
            'reference_number' => 'L1',
            'expires_on' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $cert = QualityCertificate::query()->firstOrFail();
        $this->assertSame('expired', $cert->expiryStatus());
    }

    public function test_incident_report_and_closure_rules(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/quality/incidents', [
            'description' => 'Spill',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/quality/incidents', [
            'description' => 'Spill', 'location' => 'Bay 1',
        ])->assertRedirect();
        $incident = QualityIncident::query()->firstOrFail();
        $this->actingAs($this->adminA)->post('/quality/incidents/'.$incident->id.'/decide', ['approve' => true])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/quality/incidents/'.$incident->id.'/request-closure', [
            'investigation_notes' => 'Cleaned',
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->post('/quality/incidents/'.$incident->id.'/decide', ['approve' => true])->assertForbidden();
        $this->actingAs($this->managerA)->post('/quality/incidents/'.$incident->id.'/decide', ['approve' => true])->assertRedirect();
        $this->assertSame('closed', $incident->fresh()->status);
    }

    public function test_insurance_policy_accountant_create_edit(): void
    {
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/policies', [
            'insurer' => 'ACME', 'policy_number' => 'P1',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/quality/policies', [
            'insurer' => 'ACME', 'policy_number' => 'P1', 'premium_amount' => 1000,
            'expires_on' => now()->addDays(10)->toDateString(),
        ])->assertRedirect();
        $policy = QualityInsurancePolicy::query()->firstOrFail();
        $this->assertSame('expiring_soon', $policy->expiryStatus());
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/quality/policies', [
            'insurer' => 'ACME2', 'policy_number' => 'P2',
        ])->assertRedirect();
    }

    public function test_insurance_claim_permissions_and_tenant_safe_links(): void
    {
        $policy = $this->seedRecord(QualityInsurancePolicy::class, [
            'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
            'insurer' => 'ACME', 'policy_number' => 'PX', 'created_by' => $this->accountantA->id,
        ]);
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/quality/claims', [
            'quality_insurance_policy_id' => $policy->id, 'claim_reference' => 'C1',
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/quality/claims')->assertForbidden();
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/quality/claims', [
            'quality_insurance_policy_id' => $policy->id,
            'claim_reference' => 'C1',
            'claim_amount' => 500,
            'status' => 'submitted',
        ])->assertRedirect();
        $this->assertSame(1, QualityInsuranceClaim::query()->count());

        $foreignPolicy = null;
        app(TenantContext::class)->bypass(function () use (&$foreignPolicy): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other Q', 'slug' => 'other-q', 'email' => 'oq@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchX = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'X', 'code' => 'QX', 'status' => BranchStatus::Active,
            ]);
            $foreignPolicy = QualityInsurancePolicy::query()->create([
                'tenant_id' => $tenantB->id, 'branch_id' => $branchX->id,
                'insurer' => 'Other', 'policy_number' => 'FOREIGN', 'created_by' => $this->accountantA->id,
            ]);
        });
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/quality/claims', [
            'quality_insurance_policy_id' => $foreignPolicy->id, 'claim_reference' => 'C2',
        ])->assertNotFound();
    }

    public function test_safety_log_permissions_and_flags(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/quality/safety', [
            'log_type' => 'gas_leak', 'result' => 'pass',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/quality/safety', [
            'log_type' => 'fire_safety', 'result' => 'fail', 'next_due_on' => now()->subDay()->toDateString(),
        ])->assertRedirect();
        $log = QualitySafetyLog::query()->firstOrFail();
        $this->assertSame('fail', $log->result);
        $this->assertTrue($log->isOverdue());
    }

    public function test_sop_permissions_versions_and_attachment_access(): void
    {
        Storage::fake('local');
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/quality/sops', [
            'title' => 'SOP1',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/quality/sops')->assertOk();
        $file = UploadedFile::fake()->create('sop.pdf', 100, 'application/pdf');
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/sops', [
            'title' => 'Cold Room Entry', 'attachment' => $file,
        ])->assertRedirect();
        $doc = QualitySopDocument::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/sops', [
            'quality_sop_document_id' => $doc->id,
            'title' => 'Cold Room Entry',
            'attachment' => UploadedFile::fake()->create('sop-v2.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(2, (int) $doc->fresh()->current_version);
        $this->assertSame(2, QualitySopVersion::query()->count());
        $version = QualitySopVersion::query()->where('is_current', true)->firstOrFail();
        $this->actingAs($this->accountantA)->get('/quality/sops/versions/'.$version->id.'/download')->assertOk();
    }

    public function test_tenant_branch_isolation_and_spoofing(): void
    {
        $tpl = $this->template();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/quality/templates', [
            'name' => 'Hack', 'category' => 'incoming', 'items' => 'A',
        ])->assertForbidden();
        $foreign = null;
        app(TenantContext::class)->bypass(function () use (&$foreign): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other Q2', 'slug' => 'other-q2', 'email' => 'oq2@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchX = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'X', 'code' => 'QX2', 'status' => BranchStatus::Active,
            ]);
            $foreign = $this->user($tenantB, RoleName::Manager, 'mgr-q@example.com');
            $foreign->branches()->sync([$branchX->id]);
        });
        $this->actingAs($foreign)->post('/quality/templates/'.$tpl->id, [
            'name' => 'Stolen', 'category' => 'incoming', 'items' => 'X',
        ])->assertNotFound();
    }

    public function test_unauthorized_urls_and_audit(): void
    {
        $this->actingAs($this->gatekeeperA)->get('/quality/inspections')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/quality/traceability')->assertForbidden();
        $this->actingAs($this->adminA)->get('/quality/claims')->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/quality/templates', [
            'name' => 'Aud', 'category' => 'periodic', 'items' => "One",
        ])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'quality.template_created')->exists());
    }

    public function test_navigation_visibility(): void
    {
        $gk = collect(Navigation::modules($this->gatekeeperA))->firstWhere('key', 'quality');
        $labels = collect($gk['items'])->pluck('label')->all();
        $this->assertContains('NCR & corrective actions', $labels);
        $this->assertContains('SOP library', $labels);
        $this->assertNotContains('Inspections', $labels);
        $this->assertNotContains('Lot traceability', $labels);

        $acct = collect(Navigation::modules($this->accountantA))->firstWhere('key', 'quality');
        $acctLabels = collect($acct['items'])->pluck('label')->all();
        $this->assertContains('Insurance policies', $acctLabels);
        $this->assertContains('Insurance claims', $acctLabels);
        $this->assertNotContains('Incidents & accidents', $acctLabels);
    }

    public function test_existing_dashboard_regression(): void
    {
        $this->actingAs($this->managerA)->get('/dashboard')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/quality')->assertRedirect('/quality/ncrs');
    }

    private function template(): QualityChecklistTemplate
    {
        return app(TenantContext::class)->bypass(function () {
            $tpl = QualityChecklistTemplate::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Base',
                'category' => 'incoming',
                'is_active' => true,
                'current_version' => 1,
                'created_by' => $this->managerA->id,
            ]);
            \App\Models\Csms\QualityChecklistVersion::query()->create([
                'quality_checklist_template_id' => $tpl->id,
                'version_number' => 1,
                'items' => [
                    ['label' => 'Temp', 'required' => true],
                    ['label' => 'Pack', 'required' => false],
                ],
                'created_by' => $this->managerA->id,
            ]);

            return $tpl;
        });
    }

    private function ncr(): QualityNcr
    {
        return $this->seedRecord(QualityNcr::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'reference_number' => 'NCR-T1',
            'description' => 'Issue',
            'severity' => 'medium',
            'status' => 'open',
            'reported_by' => $this->supervisorA->id,
        ]);
    }

    private function lotWithHistory(): StorageLot
    {
        return app(TenantContext::class)->bypass(function () {
            $customer = Customer::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Cust Q',
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
            $commodity = \App\Models\Csms\Commodity::query()->create([
                'tenant_id' => $this->tenantA->id,
                'name' => 'Potato Q',
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
            $chamber = \App\Models\Csms\Chamber::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Q Chamber',
                'code' => 'QCH1',
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
            $gate = \App\Models\Csms\GateEntry::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'vehicle_no' => 'Q-1',
                'purpose' => 'inbound',
                'status' => 'inside',
                'customer_id' => $customer->id,
                'created_by' => $this->managerA->id,
            ]);
            $grn = GoodsReceipt::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'gate_entry_id' => $gate->id,
                'customer_id' => $customer->id,
                'commodity_id' => $commodity->id,
                'chamber_id' => $chamber->id,
                'number' => 'GRN-Q1',
                'quantity' => 10,
                'status' => 'posted',
                'created_by' => $this->managerA->id,
            ]);
            $lot = StorageLot::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'goods_receipt_id' => $grn->id,
                'chamber_id' => $chamber->id,
                'customer_id' => $customer->id,
                'commodity_id' => $commodity->id,
                'lot_number' => 'LOT-Q1',
                'quantity' => 10,
                'status' => 'stored',
                'received_at' => now(),
            ]);
            StockLedgerEntry::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'storage_lot_id' => $lot->id,
                'type' => 'receipt',
                'quantity' => 10,
                'balance_after' => 10,
                'reason' => 'Inbound',
                'created_by' => $this->managerA->id,
            ]);
            DeliveryOrder::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'storage_lot_id' => $lot->id,
                'customer_id' => $customer->id,
                'commodity_id' => $commodity->id,
                'number' => 'DO-Q1',
                'quantity' => 2,
                'dispatched_qty' => 2,
                'remaining_qty' => 0,
                'status' => 'dispatched',
                'created_by' => $this->managerA->id,
            ]);

            return $lot;
        });
    }

    private function seedRecord(string $model, array $attrs): mixed
    {
        return app(TenantContext::class)->bypass(fn () => $model::query()->create($attrs));
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
