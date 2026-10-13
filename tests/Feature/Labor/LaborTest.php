<?php

namespace Tests\Feature\Labor;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\LaborAdvance;
use App\Models\Csms\LaborAttendance;
use App\Models\Csms\LaborAttendanceCorrection;
use App\Models\Csms\LaborEmployee;
use App\Models\Csms\LaborLeaveRequest;
use App\Models\Csms\LaborLoadingTask;
use App\Models\Csms\LaborOvertime;
use App\Models\Csms\LaborPayrollRun;
use App\Models\Csms\LaborPpeLog;
use App\Models\Csms\LaborWageRate;
use App\Models\Csms\LaborWageSheet;
use App\Models\Csms\LaborWorkRecord;
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

class LaborTest extends TestCase
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
                'name' => 'Labor Co', 'slug' => 'labor-co', 'email' => 'labor@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'LNORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'South', 'code' => 'LSOUTH', 'status' => BranchStatus::Active,
            ]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super-l@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-l@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-l@example.com');
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2-l@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-l@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-l@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-l@example.com');
            foreach ([$this->managerA, $this->manager2, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
        });
    }

    public function test_employee_create_and_view_permissions_by_role(): void
    {
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/labor/employees', [
            'employee_code' => 'E1', 'name' => 'Worker 1',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/labor/employees', [
            'employee_code' => 'E1', 'name' => 'Worker 1',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/labor/employees')->assertOk();
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/labor/employees', [
            'employee_code' => 'E1', 'name' => 'Worker 1',
        ])->assertForbidden();

        foreach ([$this->superAdmin, $this->adminA, $this->managerA] as $actor) {
            $code = 'E-'.$actor->id;
            $this->actingAs($actor)->post('/branches/'.$this->branchA->id.'/labor/employees', [
                'employee_code' => $code,
                'name' => 'Emp '.$actor->id,
                'tenant_id' => 999,
                'branch_id' => $this->branchB->id,
            ])->assertRedirect();
            $emp = LaborEmployee::query()->where('employee_code', $code)->firstOrFail();
            $this->assertSame($this->branchA->id, (int) $emp->branch_id);
            $this->assertSame($this->tenantA->id, (int) $emp->tenant_id);
        }
        $this->assertTrue(AuditLog::query()->where('action', 'labor.employee_created')->exists());
    }

    public function test_supervisor_employee_access_limited_to_own_team(): void
    {
        $own = $this->employee('OWN-1', ['supervisor_id' => $this->supervisorA->id]);
        $other = $this->employee('OTH-1', ['supervisor_id' => $this->managerA->id]);

        $this->actingAs($this->supervisorA)->get('/labor/employees')
            ->assertOk()
            ->assertSee('OWN-1')
            ->assertDontSee('OTH-1');
        $this->actingAs($this->supervisorA)->get('/labor/employees/'.$own->id)->assertOk();
        $this->actingAs($this->supervisorA)->get('/labor/employees/'.$other->id)->assertForbidden();
    }

    public function test_accountant_wage_proposal_requires_manager_approval(): void
    {
        $emp = $this->employee('WAGE-1');
        $this->actingAs($this->accountantA)->post('/labor/employees/'.$emp->id.'/wage-rates', [
            'rate_amount' => 500,
            'effective_from' => now()->toDateString(),
            'reason' => 'Market adjust',
        ])->assertRedirect();
        $rate = LaborWageRate::query()->firstOrFail();
        $this->assertSame('proposed', $rate->status);
        $this->assertNull($emp->fresh()->currentApprovedRate());

        $this->actingAs($this->accountantA)->post('/labor/wage-rates/'.$rate->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/labor/wage-rates/'.$rate->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('approved', $rate->fresh()->status);
        $approved = app(TenantContext::class)->bypass(fn () => $emp->fresh()->currentApprovedRate());
        $this->assertNotNull($approved);
        $this->assertTrue(AuditLog::query()->where('action', 'labor.wage_rate_approved')->exists());
    }

    public function test_attendance_permissions_and_gatekeeper_restriction(): void
    {
        $regular = $this->employee('REG-1');
        $gate = $this->employee('GATE-1', ['is_gate_staff' => true]);
        $visitor = $this->employee('VIS-1', ['is_visitor_labor' => true]);

        $this->actingAs($this->accountantA)->get('/labor/attendance')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/labor/attendance', [
            'labor_employee_id' => $regular->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/labor/attendance', [
            'labor_employee_id' => $gate->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->post('/labor/attendance', [
            'labor_employee_id' => $visitor->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertRedirect();
        $this->actingAs($this->supervisorA)->post('/labor/attendance', [
            'labor_employee_id' => $regular->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
        ])->assertRedirect();
    }

    public function test_supervisor_same_day_attendance_edit_enforced(): void
    {
        $emp = $this->employee('ATT-1', ['supervisor_id' => $this->supervisorA->id]);
        $today = $this->seedRecord(LaborAttendance::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'attendance_date' => now(config('app.timezone'))->toDateString(),
            'status' => 'present',
            'recorded_by' => $this->managerA->id,
        ]);
        $yesterday = $this->seedRecord(LaborAttendance::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'attendance_date' => now(config('app.timezone'))->subDay()->toDateString(),
            'status' => 'present',
            'recorded_by' => $this->managerA->id,
        ]);

        $this->actingAs($this->supervisorA)->post('/labor/attendance/'.$today->id.'/correct', [
            'status' => 'absent',
            'reason' => 'Typo',
            'attendance_date' => now()->subDays(5)->toDateString(),
        ])->assertRedirect();
        $this->assertSame('absent', $today->fresh()->status);
        $this->assertSame(now(config('app.timezone'))->toDateString(), $today->fresh()->attendance_date->toDateString());
        $this->assertSame(1, LaborAttendanceCorrection::query()->count());

        $this->actingAs($this->supervisorA)->post('/labor/attendance/'.$yesterday->id.'/correct', [
            'status' => 'absent',
            'reason' => 'Backdate attempt',
            'attendance_date' => now()->toDateString(),
        ])->assertForbidden();
        $this->assertSame('present', $yesterday->fresh()->status);

        $this->actingAs($this->managerA)->post('/labor/attendance/'.$yesterday->id.'/correct', [
            'status' => 'half_day',
            'reason' => 'Manager correction',
        ])->assertRedirect();
    }

    public function test_shift_scheduling_and_gatekeeper_own_team(): void
    {
        $teamA = $this->employee('GS-A', ['is_gate_staff' => true, 'team_code' => 'TEAM-A']);
        $teamB = $this->employee('GS-B', ['is_gate_staff' => true, 'team_code' => 'TEAM-B']);
        $regular = $this->employee('REG-S');

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/labor/shifts', [
            'name' => 'Day', 'shift_date' => now()->toDateString(), 'starts_at' => '08:00', 'ends_at' => '16:00',
        ])->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/labor/shifts', [
            'name' => 'Gate A',
            'shift_date' => now()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'team_code' => 'TEAM-A',
            'employee_ids' => [$teamB->id],
        ])->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/labor/shifts', [
            'name' => 'Gate Fake',
            'shift_date' => now()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'team_code' => 'UNKNOWN',
        ])->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/labor/shifts', [
            'name' => 'Gate A',
            'shift_date' => now()->toDateString(),
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'team_code' => 'TEAM-A',
            'employee_ids' => [$teamA->id],
        ])->assertRedirect();

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/labor/shifts', [
            'name' => 'Ops',
            'shift_date' => now()->toDateString(),
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'employee_ids' => [$regular->id],
        ])->assertRedirect();
    }

    public function test_loading_task_permissions_and_branch_isolation(): void
    {
        $emp = $this->employee('LOAD-1');
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/labor/tasks', [
            'task_type' => 'loading', 'employee_ids' => [$emp->id],
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/labor/tasks', [
            'task_type' => 'loading',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/labor/tasks', [
            'task_type' => 'unloading',
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/labor/tasks', [
            'task_type' => 'loading',
            'reference_label' => 'GRN-9',
            'employee_ids' => [$emp->id],
            'tenant_id' => 999,
        ])->assertRedirect();
        $task = LaborLoadingTask::query()->firstOrFail();
        $this->assertSame($this->branchA->id, (int) $task->branch_id);
        $this->assertSame($this->tenantA->id, (int) $task->tenant_id);
    }

    public function test_work_done_and_wage_sheet_traceability(): void
    {
        $emp = $this->employee('WORK-1');
        $this->seedRecord(LaborWageRate::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'rate_amount' => 100,
            'rate_unit' => 'day',
            'effective_from' => now()->subDay()->toDateString(),
            'status' => 'approved',
            'proposed_by' => $this->superAdmin->id,
            'approved_by' => $this->superAdmin->id,
            'approved_at' => now(),
        ]);
        $this->seedRecord(LaborAttendance::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'recorded_by' => $this->supervisorA->id,
        ]);

        $this->actingAs($this->supervisorA)->post('/labor/work', [
            'labor_employee_id' => $emp->id,
            'quantity' => -1,
            'unit' => 'bags',
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($this->supervisorA)->post('/labor/work', [
            'labor_employee_id' => $emp->id,
            'quantity' => 12,
            'unit' => 'bags',
            'work_date' => now()->toDateString(),
        ])->assertRedirect();
        $this->assertSame(1, LaborWorkRecord::query()->count());

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/labor/wage-sheets', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/labor/wage-sheets', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertRedirect();
        $sheet = app(TenantContext::class)->bypass(fn () => LaborWageSheet::query()->with('lines')->firstOrFail());
        $this->assertSame('100.00', (string) $sheet->gross_amount);
        $this->assertStringContainsString('Approved rate', $sheet->lines->first()->source_notes);
        $this->assertStringContainsString('work qty 12', $sheet->lines->first()->source_notes);
    }

    public function test_wage_sheet_approval_limits_and_self_approval(): void
    {
        $sheet = $this->seedRecord(LaborWageSheet::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'gross_amount' => 1000,
            'status' => 'pending',
            'created_by' => $this->managerA->id,
        ]);
        $this->actingAs($this->managerA)->post('/labor/wage-sheets/'.$sheet->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/labor/wage-sheets/'.$sheet->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->manager2)->post('/labor/wage-sheets/'.$sheet->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('approved', $sheet->fresh()->status);

        $huge = $this->seedRecord(LaborWageSheet::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'gross_amount' => 999999,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
        ]);
        $this->actingAs($this->manager2)->post('/labor/wage-sheets/'.$huge->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
    }

    public function test_overtime_entry_and_approval_by_role(): void
    {
        $emp = $this->employee('OT-1');
        $this->actingAs($this->managerA)->post('/labor/overtime', [
            'labor_employee_id' => $emp->id,
            'overtime_date' => now()->toDateString(),
            'hours' => 2,
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/labor/overtime')->assertOk();
        $this->actingAs($this->accountantA)->post('/labor/overtime', [
            'labor_employee_id' => $emp->id,
            'overtime_date' => now()->toDateString(),
            'hours' => 2,
        ])->assertForbidden();

        $this->actingAs($this->gatekeeperA)->post('/labor/overtime', [
            'labor_employee_id' => $emp->id,
            'overtime_date' => now()->toDateString(),
            'hours' => 2,
            'reason' => 'Peak',
        ])->assertRedirect();
        $ot = LaborOvertime::query()->firstOrFail();

        $this->actingAs($this->gatekeeperA)->post('/labor/overtime/'.$ot->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/labor/overtime/'.$ot->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('approved', $ot->fresh()->status);

        $this->actingAs($this->gatekeeperA)->post('/labor/overtime', [
            'labor_employee_id' => $emp->id,
            'overtime_date' => now()->addDay()->toDateString(),
            'hours' => 1,
        ])->assertRedirect();
        $ot2 = LaborOvertime::query()->where('status', 'pending')->firstOrFail();
        $this->actingAs($this->adminA)->post('/labor/overtime/'.$ot2->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
    }

    public function test_labor_advances_request_approve_pay_and_outstanding(): void
    {
        $emp = $this->employee('ADV-1');
        $this->actingAs($this->supervisorA)->post('/labor/advances', [
            'labor_employee_id' => $emp->id,
            'amount' => 200,
            'reason' => 'Urgent',
        ])->assertRedirect();
        $advance = LaborAdvance::query()->firstOrFail();
        $this->assertSame('requested', $advance->status);

        $this->actingAs($this->supervisorA)->post('/labor/advances/'.$advance->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/labor/advances/'.$advance->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('approved', $advance->fresh()->status);

        $this->actingAs($this->accountantA)->post('/labor/advances/'.$advance->id.'/pay')->assertRedirect();
        $this->assertSame('paid', $advance->fresh()->status);
        $this->assertEquals(200.0, (float) $advance->fresh()->outstanding_amount);

        $this->actingAs($this->accountantA)->post('/labor/advances', [
            'labor_employee_id' => $emp->id,
            'amount' => 50,
        ])->assertRedirect();
    }

    public function test_payroll_duplicate_finalization_and_lock(): void
    {
        $emp = $this->employee('PAY-1');
        $this->seedRecord(LaborWageRate::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'rate_amount' => 300,
            'rate_unit' => 'day',
            'effective_from' => now()->subDay()->toDateString(),
            'status' => 'approved',
            'proposed_by' => $this->superAdmin->id,
            'approved_by' => $this->superAdmin->id,
            'approved_at' => now(),
        ]);
        $this->seedRecord(LaborAttendance::class, [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'labor_employee_id' => $emp->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'recorded_by' => $this->managerA->id,
        ]);
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/labor/wage-sheets', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertRedirect();
        $sheet = LaborWageSheet::query()->firstOrFail();
        $this->actingAs($this->manager2)->post('/labor/wage-sheets/'.$sheet->id.'/decide', ['approve' => true])->assertRedirect();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/labor/payroll', [
            'labor_wage_sheet_id' => $sheet->id,
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/labor/payroll', [
            'labor_wage_sheet_id' => $sheet->id,
            'tenant_id' => 999,
            'status' => 'finalized',
        ])->assertRedirect();
        $run = app(TenantContext::class)->bypass(fn () => LaborPayrollRun::query()->firstOrFail());
        $this->assertSame('pending', $run->status);
        $this->assertEquals(300.0, (float) $run->gross_amount);

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/labor/payroll', [
            'labor_wage_sheet_id' => $sheet->id,
        ])->assertSessionHasErrors('period_start');

        $this->actingAs($this->accountantA)->post('/labor/payroll/'.$run->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/labor/payroll/'.$run->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('finalized', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->finalized_at);

        $this->actingAs($this->managerA)->post('/labor/payroll/'.$run->id.'/decide', [
            'approve' => true,
        ])->assertStatus(422);
    }

    public function test_ppe_and_safety_training_permissions(): void
    {
        $emp = $this->employee('PPE-1');
        $this->actingAs($this->gatekeeperA)->post('/labor/ppe', [
            'labor_employee_id' => $emp->id,
            'item_or_training' => 'Helmet',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->get('/labor/ppe')->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/labor/ppe', [
            'labor_employee_id' => $emp->id,
            'item_or_training' => 'Helmet',
            'log_type' => 'ppe',
            'quantity' => 1,
            'expires_on' => now()->addYear()->toDateString(),
        ])->assertRedirect();
        $this->assertSame(1, LaborPpeLog::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'labor.ppe_logged')->exists());
    }

    public function test_leave_request_approval_and_date_validation(): void
    {
        $emp = $this->employee('LEAVE-1');
        $this->actingAs($this->accountantA)->post('/labor/leave', [
            'labor_employee_id' => $emp->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/labor/leave', [
            'labor_employee_id' => $emp->id,
            'starts_on' => now()->addDays(2)->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors();
        $this->actingAs($this->supervisorA)->post('/labor/leave', [
            'labor_employee_id' => $emp->id,
            'leave_type' => 'unpaid',
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
            'reason' => 'Family',
        ])->assertRedirect();
        $leave = LaborLeaveRequest::query()->firstOrFail();
        $this->actingAs($this->supervisorA)->post('/labor/leave/'.$leave->id.'/decide', [
            'approve' => true,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/labor/leave/'.$leave->id.'/decide', [
            'approve' => true,
        ])->assertRedirect();
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_tenant_branch_isolation_and_spoofing(): void
    {
        $emp = $this->employee('ISO-1');
        $foreign = null;
        app(TenantContext::class)->bypass(function () use (&$foreign): void {
            $tenantB = Tenant::query()->create([
                'name' => 'Other L', 'slug' => 'other-l', 'email' => 'ol@example.com', 'status' => TenantStatus::Active,
            ]);
            $branchX = Branch::query()->create([
                'tenant_id' => $tenantB->id, 'name' => 'X', 'code' => 'LX1', 'status' => BranchStatus::Active,
            ]);
            $foreign = $this->user($tenantB, RoleName::Manager, 'mgr-l@example.com');
            $foreign->branches()->sync([$branchX->id]);
        });

        $this->actingAs($foreign)->get('/labor/employees/'.$emp->id)->assertNotFound();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/labor/employees', [
            'employee_code' => 'HACK',
            'name' => 'Hack',
        ])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/labor/employees/'.$emp->id.'/wage-rates', [
            'rate_amount' => 10,
            'status' => 'approved',
            'approved_by' => $this->accountantA->id,
        ])->assertRedirect();
        $this->assertSame('proposed', LaborWageRate::query()->latest('id')->first()->status);
    }

    public function test_audit_logging_and_unauthorized_direct_urls(): void
    {
        $emp = $this->employee('AUD-1');
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/labor/employees', [
            'employee_code' => 'AUD-2', 'name' => 'Audited',
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->get('/labor/employees')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/labor/payroll')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/labor/wage-sheets')->assertForbidden();
        $this->actingAs($this->adminA)->get('/labor/payroll')->assertForbidden();
        $this->actingAs($this->managerA)->post('/labor/employees/'.$emp->id.'/wage-rates', [
            'rate_amount' => 50,
        ])->assertForbidden();
        $this->assertTrue(AuditLog::query()->where('action', 'labor.employee_created')->exists());
    }

    public function test_navigation_visibility_matches_permissions(): void
    {
        $gk = collect(Navigation::modules($this->gatekeeperA))->firstWhere('key', 'labor');
        $this->assertNotNull($gk);
        $labels = collect($gk['items'])->pluck('label')->all();
        $this->assertContains('Attendance', $labels);
        $this->assertContains('Shift scheduling', $labels);
        $this->assertContains('Overtime', $labels);
        $this->assertContains('Leave management', $labels);
        $this->assertNotContains('Payroll', $labels);
        $this->assertNotContains('Employee / labor master', $labels);

        $acct = collect(Navigation::modules($this->accountantA))->firstWhere('key', 'labor');
        $acctLabels = collect($acct['items'])->pluck('label')->all();
        $this->assertContains('Employee / labor master', $acctLabels);
        $this->assertContains('Payroll', $acctLabels);
        $this->assertNotContains('Attendance', $acctLabels);

        $mgr = collect(Navigation::modules($this->managerA))->firstWhere('key', 'labor');
        $mgrLabels = collect($mgr['items'])->pluck('label')->all();
        $this->assertNotContains('Shift scheduling', $mgrLabels);
        $this->assertNotContains('Overtime', $mgrLabels);
        $this->assertContains('Payroll', $mgrLabels);
    }

    public function test_existing_dashboard_still_loads(): void
    {
        $this->actingAs($this->managerA)->get('/dashboard')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/labor')->assertRedirect('/labor/attendance');
    }

    private function employee(string $code, array $extra = []): LaborEmployee
    {
        return app(TenantContext::class)->bypass(fn () => LaborEmployee::query()->create(array_merge([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'employee_code' => $code,
            'name' => $code,
            'engagement_type' => 'daily_wage',
            'status' => 'active',
            'is_gate_staff' => false,
            'is_visitor_labor' => false,
            'created_by' => $this->managerA->id,
        ], $extra)));
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, mixed>  $attrs
     */
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
