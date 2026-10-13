<?php

namespace App\Services\Labor;

use App\Enums\RoleName;
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
use App\Models\Csms\LaborShift;
use App\Models\Csms\LaborWageRate;
use App\Models\Csms\LaborWageSheet;
use App\Models\Csms\LaborWageSheetLine;
use App\Models\Csms\LaborWorkRecord;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaborDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
    ) {}

    public function canCreateEmployee(User $user): bool
    {
        return $user->hasPermission('labor.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewEmployees(User $user): bool
    {
        return $user->hasPermission('labor.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Accountant,
            ]));
    }

    public function canViewWageRates(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]);
    }

    public function canProposeWageRate(User $user): bool
    {
        return $user->hasPermission('labor.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveWageRate(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canMarkAttendance(User $user): bool
    {
        return $user->hasPermission('labor.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canEditAttendance(User $user): bool
    {
        return $user->hasPermission('labor.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canManageShifts(User $user): bool
    {
        return ($user->hasPermission('labor.create') || $user->hasPermission('labor.edit'))
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canAssignLoading(User $user): bool
    {
        return $user->hasPermission('labor.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canRecordWork(User $user): bool
    {
        return $user->hasPermission('labor.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveWageSheet(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEnterOvertime(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('labor.create') && $user->hasRole(RoleName::Gatekeeper));
    }

    public function canApproveOvertime(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Supervisor]));
    }

    public function canViewOvertime(User $user): bool
    {
        return $this->canEnterOvertime($user)
            || $this->canApproveOvertime($user)
            || ($user->hasRole(RoleName::Accountant) && $user->hasPermission('labor.view'));
    }

    public function canRequestAdvance(User $user): bool
    {
        return $user->hasPermission('labor.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Supervisor, RoleName::Accountant,
            ]));
    }

    public function canApproveAdvance(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canExecutePayroll(User $user): bool
    {
        return $user->hasPermission('labor.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApprovePayroll(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canLogPpe(User $user): bool
    {
        return $user->hasPermission('labor.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canRequestLeave(User $user): bool
    {
        return $user->hasPermission('labor.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Supervisor, RoleName::Gatekeeper]));
    }

    public function canApproveLeave(User $user): bool
    {
        return $user->hasPermission('labor.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function employeesVisibleTo(User $user): Builder
    {
        $query = LaborEmployee::query()->visibleTo($user);
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin()
            && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $query->where('supervisor_id', $user->id);
        }

        return $query;
    }

    public function createEmployee(User $actor, Branch $branch, array $data): LaborEmployee
    {
        abort_unless($this->canCreateEmployee($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $code = strtoupper(trim($data['employee_code']));
        if (LaborEmployee::query()->where('tenant_id', $branch->tenant_id)->where('employee_code', $code)->exists()) {
            throw ValidationException::withMessages(['employee_code' => 'Employee code already exists for this tenant.']);
        }

        $employee = LaborEmployee::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'employee_code' => $code,
            'name' => $data['name'],
            'labor_category' => $data['labor_category'] ?? null,
            'engagement_type' => $data['engagement_type'] ?? 'daily_wage',
            'phone' => $data['phone'] ?? null,
            'team_code' => $data['team_code'] ?? null,
            'supervisor_id' => $data['supervisor_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_gate_staff' => (bool) ($data['is_gate_staff'] ?? false),
            'is_visitor_labor' => (bool) ($data['is_visitor_labor'] ?? false),
            'created_by' => $actor->id,
        ]);
        $this->audit->record('labor.employee_created', $employee, [], $branch->tenant_id, [
            'module' => 'labor', 'branch_id' => $branch->id,
        ]);

        return $employee;
    }

    public function proposeWageRate(User $actor, LaborEmployee $employee, array $data): LaborWageRate
    {
        abort_unless($this->canProposeWageRate($actor), 403);
        // SA can set wage directly as approved; Accountant proposes.
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $isSa = $actor->isSuperAdmin();
        $rate = LaborWageRate::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'rate_amount' => $data['rate_amount'],
            'rate_unit' => $data['rate_unit'] ?? 'day',
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'status' => $isSa ? 'approved' : 'proposed',
            'reason' => $data['reason'] ?? null,
            'proposed_by' => $actor->id,
            'approved_by' => $isSa ? $actor->id : null,
            'approved_at' => $isSa ? now() : null,
        ]);
        $this->audit->record('labor.wage_rate_proposed', $rate, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $rate;
    }

    public function approveWageRate(User $actor, LaborWageRate $rate, bool $approve): void
    {
        abort_unless($this->canApproveWageRate($actor), 403);
        $this->assertBranchAccess($actor, (int) $rate->branch_id);
        abort_unless($rate->status === 'proposed', 422);
        $this->makerChecker->assertCanApprove($actor, $rate->proposed_by);
        $rate->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $this->audit->record($approve ? 'labor.wage_rate_approved' : 'labor.wage_rate_rejected', $rate, [], $rate->tenant_id, [
            'module' => 'labor', 'branch_id' => $rate->branch_id,
        ]);
    }

    public function markAttendance(User $actor, LaborEmployee $employee, array $data): LaborAttendance
    {
        abort_unless($this->canMarkAttendance($actor), 403);
        $this->assertBranchAccess($actor, (int) $employee->branch_id);

        if ($actor->hasRole(RoleName::Gatekeeper) && ! $actor->isSuperAdmin()
            && ! $actor->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor])) {
            abort_unless($employee->is_gate_staff || $employee->is_visitor_labor, 403);
        }

        $date = Carbon::parse($data['attendance_date'])->toDateString();
        $shiftId = $data['labor_shift_id'] ?? null;
        $exists = LaborAttendance::query()
            ->where('labor_employee_id', $employee->id)
            ->where('attendance_date', $date)
            ->where(fn ($q) => $shiftId ? $q->where('labor_shift_id', $shiftId) : $q->whereNull('labor_shift_id'))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['attendance_date' => 'Attendance already recorded for this employee/date/shift.']);
        }

        $row = LaborAttendance::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'labor_shift_id' => $shiftId,
            'attendance_date' => $date,
            'status' => $data['status'] ?? 'present',
            'check_in_at' => $data['check_in_at'] ?? null,
            'check_out_at' => $data['check_out_at'] ?? null,
            'subject_type' => $employee->is_visitor_labor ? 'visitor' : ($employee->is_gate_staff ? 'gate_staff' : 'employee'),
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record('labor.attendance_marked', $row, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $row;
    }

    public function correctAttendance(User $actor, LaborAttendance $attendance, array $data, string $reason): void
    {
        abort_unless($this->canEditAttendance($actor), 403);
        $this->assertBranchAccess($actor, (int) $attendance->branch_id);
        abort_unless(filled($reason), 422);

        if ($actor->hasRole(RoleName::Supervisor) && ! $actor->isSuperAdmin()
            && ! $actor->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            $today = now(config('app.timezone'))->toDateString();
            abort_unless($attendance->attendance_date->toDateString() === $today, 403);
            // Ignore client-supplied date changes for same-day rule.
            unset($data['attendance_date']);
        }

        $original = $attendance->only(['status', 'check_in_at', 'check_out_at', 'attendance_date']);
        $attendance->update(array_intersect_key($data, array_flip(['status', 'check_in_at', 'check_out_at'])));
        LaborAttendanceCorrection::query()->create([
            'labor_attendance_id' => $attendance->id,
            'original_values' => $original,
            'corrected_values' => $attendance->only(['status', 'check_in_at', 'check_out_at', 'attendance_date']),
            'reason' => $reason,
            'corrected_by' => $actor->id,
        ]);
        $this->audit->record('labor.attendance_corrected', $attendance, $original, $attendance->tenant_id, [
            'module' => 'labor', 'branch_id' => $attendance->branch_id, 'reason' => $reason,
            'new' => $attendance->only(['status', 'check_in_at', 'check_out_at']),
        ]);
    }

    public function createShift(User $actor, Branch $branch, array $data): LaborShift
    {
        abort_unless($this->canManageShifts($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $team = $data['team_code'] ?? null;
        $isGatekeeper = $actor->hasRole(RoleName::Gatekeeper) && ! $actor->isSuperAdmin()
            && ! $actor->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor]);
        if ($isGatekeeper) {
            abort_unless(filled($team), 422);
            $teamExists = LaborEmployee::query()
                ->where('branch_id', $branch->id)
                ->where('team_code', $team)
                ->where(fn ($q) => $q->where('is_gate_staff', true)->orWhere('is_visitor_labor', true))
                ->exists();
            abort_unless($teamExists, 403);
        }

        $overlap = LaborShift::query()
            ->where('branch_id', $branch->id)
            ->where('shift_date', $data['shift_date'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->when($team, fn ($q) => $q->where('team_code', $team))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Overlapping shift exists for this date/team.']);
        }

        $shift = LaborShift::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'shift_date' => $data['shift_date'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'team_code' => $team,
            'created_by' => $actor->id,
        ]);
        if (! empty($data['employee_ids']) && is_array($data['employee_ids'])) {
            foreach ($data['employee_ids'] as $empId) {
                $employee = LaborEmployee::query()->findOrFail($empId);
                abort_unless((int) $employee->branch_id === (int) $branch->id, 422);
                if ($isGatekeeper) {
                    abort_unless(
                        ($employee->is_gate_staff || $employee->is_visitor_labor) && $employee->team_code === $team,
                        403
                    );
                }
                $shift->employees()->attach($empId);
            }
        }
        $this->audit->record('labor.shift_created', $shift, [], $branch->tenant_id, [
            'module' => 'labor', 'branch_id' => $branch->id,
        ]);

        return $shift;
    }

    public function createLoadingTask(User $actor, Branch $branch, array $data): LaborLoadingTask
    {
        abort_unless($this->canAssignLoading($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $task = LaborLoadingTask::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'task_type' => $data['task_type'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_label' => $data['reference_label'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => 'open',
            'created_by' => $actor->id,
        ]);
        foreach (($data['employee_ids'] ?? []) as $empId) {
            $employee = LaborEmployee::query()->findOrFail($empId);
            abort_unless((int) $employee->branch_id === (int) $branch->id, 422);
            $task->employees()->attach($empId);
        }
        $this->audit->record('labor.loading_task_created', $task, [], $branch->tenant_id, [
            'module' => 'labor', 'branch_id' => $branch->id,
        ]);

        return $task;
    }

    public function recordWork(User $actor, LaborEmployee $employee, array $data): LaborWorkRecord
    {
        abort_unless($this->canRecordWork($actor), 403);
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $qty = (float) $data['quantity'];
        if ($qty < 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be non-negative.']);
        }

        $row = LaborWorkRecord::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_loading_task_id' => $data['labor_loading_task_id'] ?? null,
            'labor_employee_id' => $employee->id,
            'work_date' => $data['work_date'] ?? now()->toDateString(),
            'quantity' => $qty,
            'unit' => $data['unit'] ?? 'bags',
            'status' => 'recorded',
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record('labor.work_recorded', $row, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $row;
    }

    public function generateWageSheet(User $actor, Branch $branch, string $from, string $to): LaborWageSheet
    {
        abort_unless($actor->isSuperAdmin() || $actor->hasRole(RoleName::Manager), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        return DB::transaction(function () use ($actor, $branch, $from, $to) {
            $sheet = LaborWageSheet::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'period_start' => $from,
                'period_end' => $to,
                'gross_amount' => 0,
                'status' => 'pending',
                'calculation_notes' => 'Built from approved wage rates × present attendance days. Overtime multipliers are not configured.',
                'created_by' => $actor->id,
            ]);

            $employees = LaborEmployee::query()->where('branch_id', $branch->id)->where('status', 'active')->get();
            $gross = 0.0;
            foreach ($employees as $employee) {
                $days = LaborAttendance::query()
                    ->where('labor_employee_id', $employee->id)
                    ->whereDate('attendance_date', '>=', $from)
                    ->whereDate('attendance_date', '<=', $to)
                    ->where('status', 'present')
                    ->count();
                $workQty = (float) LaborWorkRecord::query()
                    ->where('labor_employee_id', $employee->id)
                    ->whereDate('work_date', '>=', $from)
                    ->whereDate('work_date', '<=', $to)
                    ->where('status', 'recorded')
                    ->sum('quantity');
                $rate = $employee->currentApprovedRate();
                if (! $rate || $days <= 0) {
                    continue;
                }
                $lineAmount = round((float) $rate->rate_amount * $days, 2);
                $gross += $lineAmount;
                LaborWageSheetLine::query()->create([
                    'labor_wage_sheet_id' => $sheet->id,
                    'labor_employee_id' => $employee->id,
                    'days_present' => $days,
                    'work_quantity' => $workQty,
                    'rate_amount' => $rate->rate_amount,
                    'line_amount' => $lineAmount,
                    'source_notes' => "Approved rate {$rate->rate_amount}/{$rate->rate_unit} × {$days} present day(s); work qty {$workQty} (informational).",
                ]);
            }
            $sheet->update(['gross_amount' => $gross]);
            $this->audit->record('labor.wage_sheet_generated', $sheet, [], $branch->tenant_id, [
                'module' => 'labor', 'branch_id' => $branch->id,
            ]);

            return $sheet->fresh('lines');
        });
    }

    public function approveWageSheet(User $actor, LaborWageSheet $sheet, bool $approve): void
    {
        abort_unless($this->canApproveWageSheet($actor), 403);
        $this->assertBranchAccess($actor, (int) $sheet->branch_id);
        abort_unless($sheet->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $sheet->created_by);
        $this->limits->assertCanApprove($actor, 'wage_sheet', $sheet->gross_amount);
        $sheet->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $this->audit->record($approve ? 'labor.wage_sheet_approved' : 'labor.wage_sheet_rejected', $sheet, [], $sheet->tenant_id, [
            'module' => 'labor', 'branch_id' => $sheet->branch_id,
        ]);
    }

    public function enterOvertime(User $actor, LaborEmployee $employee, array $data): LaborOvertime
    {
        abort_unless($this->canEnterOvertime($actor) || $actor->isSuperAdmin(), 403);
        if (! $actor->isSuperAdmin()) {
            abort_unless($this->canEnterOvertime($actor), 403);
        }
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $hours = (float) $data['hours'];
        if ($hours <= 0) {
            throw ValidationException::withMessages(['hours' => 'Hours must be positive.']);
        }
        $row = LaborOvertime::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'overtime_date' => $data['overtime_date'],
            'hours' => $hours,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('labor.overtime_entered', $row, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $row;
    }

    public function approveOvertime(User $actor, LaborOvertime $row, bool $approve): void
    {
        abort_unless($this->canApproveOvertime($actor), 403);
        $this->assertBranchAccess($actor, (int) $row->branch_id);
        abort_unless($row->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $row->created_by);
        $row->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $this->audit->record($approve ? 'labor.overtime_approved' : 'labor.overtime_rejected', $row, [], $row->tenant_id, [
            'module' => 'labor', 'branch_id' => $row->branch_id,
        ]);
    }

    public function requestAdvance(User $actor, LaborEmployee $employee, array $data): LaborAdvance
    {
        abort_unless($this->canRequestAdvance($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be positive.']);
        }
        $status = $actor->isSuperAdmin() ? 'approved' : 'requested';
        $advance = LaborAdvance::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => $status,
            'reason' => $data['reason'] ?? null,
            'requested_by' => $actor->id,
            'approved_by' => $actor->isSuperAdmin() ? $actor->id : null,
            'approved_at' => $actor->isSuperAdmin() ? now() : null,
        ]);
        $this->audit->record('labor.advance_requested', $advance, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $advance;
    }

    public function approveAdvance(User $actor, LaborAdvance $advance, bool $approve): void
    {
        abort_unless($this->canApproveAdvance($actor), 403);
        $this->assertBranchAccess($actor, (int) $advance->branch_id);
        abort_unless($advance->status === 'requested', 422);
        $this->makerChecker->assertCanApprove($actor, $advance->requested_by);
        $this->limits->assertCanApprove($actor, 'wage_advance', $advance->amount);
        $advance->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $this->audit->record($approve ? 'labor.advance_approved' : 'labor.advance_rejected', $advance, [], $advance->tenant_id, [
            'module' => 'labor', 'branch_id' => $advance->branch_id,
        ]);
    }

    public function markAdvancePaid(User $actor, LaborAdvance $advance): void
    {
        abort_unless(($this->canRequestAdvance($actor) && $actor->hasRole(RoleName::Accountant)) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $advance->branch_id);
        abort_unless($advance->status === 'approved', 422);
        $advance->update(['status' => 'paid', 'paid_at' => now()]);
        $this->audit->record('labor.advance_paid', $advance, [], $advance->tenant_id, [
            'module' => 'labor', 'branch_id' => $advance->branch_id,
        ]);
    }

    public function executePayroll(User $actor, Branch $branch, LaborWageSheet $sheet): LaborPayrollRun
    {
        abort_unless($this->canExecutePayroll($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        abort_unless($sheet->status === 'approved', 422);
        abort_unless((int) $sheet->branch_id === (int) $branch->id, 422);

        $exists = LaborPayrollRun::query()
            ->where('branch_id', $branch->id)
            ->whereDate('period_start', $sheet->period_start->toDateString())
            ->whereDate('period_end', $sheet->period_end->toDateString())
            ->whereIn('status', ['draft', 'pending', 'approved', 'finalized'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['period_start' => 'Payroll already exists for this period.']);
        }

        return DB::transaction(function () use ($actor, $branch, $sheet) {
            $advances = (float) LaborAdvance::query()
                ->where('branch_id', $branch->id)
                ->where('status', 'paid')
                ->where('outstanding_amount', '>', 0)
                ->sum('outstanding_amount');
            $deduct = min($advances, (float) $sheet->gross_amount);
            $run = LaborPayrollRun::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'period_start' => $sheet->period_start,
                'period_end' => $sheet->period_end,
                'labor_wage_sheet_id' => $sheet->id,
                'gross_amount' => $sheet->gross_amount,
                'advances_deducted' => $deduct,
                'net_payable' => round((float) $sheet->gross_amount - $deduct, 2),
                'status' => 'pending',
                'calculation_notes' => 'Gross from approved wage sheet; advances deducted from outstanding paid advances. No tax/statutory rules configured.',
                'executed_by' => $actor->id,
            ]);
            if ($deduct > 0) {
                $remaining = $deduct;
                $paidAdvances = LaborAdvance::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', 'paid')
                    ->where('outstanding_amount', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                foreach ($paidAdvances as $adv) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $take = min((float) $adv->outstanding_amount, $remaining);
                    $adv->update([
                        'outstanding_amount' => round((float) $adv->outstanding_amount - $take, 2),
                        'status' => ((float) $adv->outstanding_amount - $take) <= 0.001 ? 'adjusted' : 'paid',
                    ]);
                    $remaining -= $take;
                }
            }
            $this->audit->record('labor.payroll_executed', $run, [], $branch->tenant_id, [
                'module' => 'labor', 'branch_id' => $branch->id,
            ]);

            return $run;
        });
    }

    public function approvePayroll(User $actor, LaborPayrollRun $run, bool $approve): void
    {
        abort_unless($this->canApprovePayroll($actor), 403);
        $this->assertBranchAccess($actor, (int) $run->branch_id);
        abort_unless($run->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $run->executed_by);
        if (! $approve) {
            $run->update(['status' => 'rejected', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record('labor.payroll_rejected', $run, [], $run->tenant_id, [
                'module' => 'labor', 'branch_id' => $run->branch_id,
            ]);

            return;
        }
        $run->update([
            'status' => 'finalized',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'finalized_at' => now(),
        ]);
        $this->audit->record('labor.payroll_finalized', $run, [], $run->tenant_id, [
            'module' => 'labor', 'branch_id' => $run->branch_id,
        ]);
    }

    public function logPpe(User $actor, LaborEmployee $employee, array $data): LaborPpeLog
    {
        abort_unless($this->canLogPpe($actor), 403);
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $log = LaborPpeLog::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'item_or_training' => $data['item_or_training'],
            'log_type' => $data['log_type'] ?? 'ppe',
            'logged_on' => $data['logged_on'] ?? now()->toDateString(),
            'quantity' => $data['quantity'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record('labor.ppe_logged', $log, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $log;
    }

    public function requestLeave(User $actor, LaborEmployee $employee, array $data): LaborLeaveRequest
    {
        abort_unless($this->canRequestLeave($actor), 403);
        $this->assertBranchAccess($actor, (int) $employee->branch_id);
        $start = Carbon::parse($data['starts_on']);
        $end = Carbon::parse($data['ends_on']);
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['ends_on' => 'End date must be on or after start date.']);
        }
        $overlap = LaborLeaveRequest::query()
            ->where('labor_employee_id', $employee->id)
            ->whereIn('status', ['requested', 'approved'])
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'Overlapping leave request exists.']);
        }
        $leave = LaborLeaveRequest::query()->create([
            'tenant_id' => $employee->tenant_id,
            'branch_id' => $employee->branch_id,
            'labor_employee_id' => $employee->id,
            'leave_type' => $data['leave_type'] ?? 'unpaid',
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'reason' => $data['reason'] ?? null,
            'status' => 'requested',
            'requested_by' => $actor->id,
        ]);
        $this->audit->record('labor.leave_requested', $leave, [], $employee->tenant_id, [
            'module' => 'labor', 'branch_id' => $employee->branch_id,
        ]);

        return $leave;
    }

    public function decideLeave(User $actor, LaborLeaveRequest $leave, bool $approve): void
    {
        abort_unless($this->canApproveLeave($actor), 403);
        $this->assertBranchAccess($actor, (int) $leave->branch_id);
        abort_unless($leave->status === 'requested', 422);
        $this->makerChecker->assertCanApprove($actor, $leave->requested_by);
        $leave->update([
            'status' => $approve ? 'approved' : 'rejected',
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $this->audit->record($approve ? 'labor.leave_approved' : 'labor.leave_rejected', $leave, [], $leave->tenant_id, [
            'module' => 'labor', 'branch_id' => $leave->branch_id,
        ]);
    }
}
