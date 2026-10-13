<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\LaborAdvance;
use App\Models\Csms\LaborAttendance;
use App\Models\Csms\LaborEmployee;
use App\Models\Csms\LaborLeaveRequest;
use App\Models\Csms\LaborLoadingTask;
use App\Models\Csms\LaborOvertime;
use App\Models\Csms\LaborPayrollRun;
use App\Models\Csms\LaborWageRate;
use App\Models\Csms\LaborWageSheet;
use App\Models\User;
use App\Services\Labor\LaborDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaborController extends Controller
{
    public function __construct(private readonly LaborDesk $desk) {}

    public function employees(Request $request): View
    {
        abort_unless($this->desk->canViewEmployees($request->user()) || $this->desk->canCreateEmployee($request->user()), 403);
        $user = $request->user();

        return view('labor.employees', [
            'employees' => $this->desk->employeesVisibleTo($user)->latest()->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'supervisors' => User::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->limit(50)->get(),
            'desk' => $this->desk,
            'showWages' => $this->desk->canViewWageRates($user),
        ]);
    }

    public function storeEmployee(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'labor_category' => ['nullable', 'string', 'max:100'],
            'engagement_type' => ['nullable', 'in:permanent,temporary,daily_wage'],
            'phone' => ['nullable', 'string', 'max:50'],
            'team_code' => ['nullable', 'string', 'max:50'],
            'supervisor_id' => ['nullable', 'integer'],
            'is_gate_staff' => ['nullable', 'boolean'],
            'is_visitor_labor' => ['nullable', 'boolean'],
        ]);
        $data['is_gate_staff'] = $request->boolean('is_gate_staff');
        $data['is_visitor_labor'] = $request->boolean('is_visitor_labor');
        $this->desk->createEmployee($request->user(), $branch, $data);

        return back()->with('status', 'Employee created.');
    }

    public function showEmployee(LaborEmployee $labor_employee): View
    {
        $user = request()->user();
        abort_unless($this->desk->canViewEmployees($user), 403);
        $this->desk->assertBranchAccess($user, (int) $labor_employee->branch_id);
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager])) {
            abort_unless((int) $labor_employee->supervisor_id === (int) $user->id, 403);
        }
        $labor_employee->load(['wageRates' => fn ($q) => $q->latest()]);

        return view('labor.employee-show', [
            'employee' => $labor_employee,
            'desk' => $this->desk,
            'showWages' => $this->desk->canViewWageRates($user),
        ]);
    }

    public function storeWageRate(Request $request, LaborEmployee $labor_employee): RedirectResponse
    {
        $data = $request->validate([
            'rate_amount' => ['required', 'numeric', 'min:0.01'],
            'rate_unit' => ['nullable', 'string', 'max:20'],
            'effective_from' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->proposeWageRate($request->user(), $labor_employee, $data);

        return back()->with('status', 'Wage rate submitted.');
    }

    public function decideWageRate(Request $request, LaborWageRate $labor_wage_rate): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveWageRate($request->user(), $labor_wage_rate, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Wage rate approved.' : 'Wage rate rejected.');
    }

    public function attendance(Request $request): View
    {
        abort_unless($this->desk->canMarkAttendance($request->user()) || $this->desk->canEditAttendance($request->user()), 403);
        $user = $request->user();
        $employees = $this->desk->employeesVisibleTo($user);
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin()
            && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor])) {
            $employees = LaborEmployee::query()->visibleTo($user)
                ->where(fn ($q) => $q->where('is_gate_staff', true)->orWhere('is_visitor_labor', true));
        }

        return view('labor.attendance', [
            'rows' => LaborAttendance::query()->visibleTo($user)->with('employee')->latest('attendance_date')->limit(80)->get(),
            'employees' => $employees->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'attendance_date' => ['required', 'date'],
            'status' => ['nullable', 'in:present,absent,half_day,leave'],
            'labor_shift_id' => ['nullable', 'integer'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->markAttendance($request->user(), $employee, $data);

        return back()->with('status', 'Attendance recorded.');
    }

    public function correctAttendance(Request $request, LaborAttendance $labor_attendance): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:present,absent,half_day,leave'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'attendance_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        $this->desk->correctAttendance($request->user(), $labor_attendance, $data, $reason);

        return back()->with('status', 'Attendance corrected.');
    }

    public function shifts(Request $request): View
    {
        abort_unless($this->desk->canManageShifts($request->user()), 403);
        $user = $request->user();

        return view('labor.shifts', [
            'shifts' => \App\Models\Csms\LaborShift::query()->visibleTo($user)->with('employees')->latest('shift_date')->limit(50)->get(),
            'employees' => $this->desk->employeesVisibleTo($user)->orderBy('name')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeShift(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'shift_date' => ['required', 'date'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'team_code' => ['nullable', 'string', 'max:50'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer'],
        ]);
        $this->desk->createShift($request->user(), $branch, $data);

        return back()->with('status', 'Shift scheduled.');
    }

    public function tasks(Request $request): View
    {
        abort_unless($this->desk->canAssignLoading($request->user()) || $this->desk->canRecordWork($request->user()), 403);
        $user = $request->user();

        return view('labor.tasks', [
            'tasks' => LaborLoadingTask::query()->visibleTo($user)->with('employees')->latest()->limit(50)->get(),
            'work' => \App\Models\Csms\LaborWorkRecord::query()->visibleTo($user)->with('employee')->latest('work_date')->limit(50)->get(),
            'employees' => $this->desk->employeesVisibleTo($user)->orderBy('name')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeTask(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'task_type' => ['required', 'in:loading,unloading'],
            'reference_label' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'scheduled_at' => ['nullable', 'date'],
            'employee_ids' => ['nullable', 'array'],
        ]);
        $this->desk->createLoadingTask($request->user(), $branch, $data);

        return back()->with('status', 'Task assigned.');
    }

    public function storeWork(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'labor_loading_task_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'work_date' => ['nullable', 'date'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->recordWork($request->user(), $employee, $data);

        return back()->with('status', 'Work recorded.');
    }

    public function wageSheets(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canApproveWageSheet($user) || $user->isSuperAdmin() || $user->hasRole(RoleName::Manager), 403);

        return view('labor.wage-sheets', [
            'sheets' => LaborWageSheet::query()->visibleTo($user)->with('lines.employee')->latest()->limit(40)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeWageSheet(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $this->desk->generateWageSheet($request->user(), $branch, $data['period_start'], $data['period_end']);

        return back()->with('status', 'Wage sheet generated.');
    }

    public function decideWageSheet(Request $request, LaborWageSheet $labor_wage_sheet): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveWageSheet($request->user(), $labor_wage_sheet, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Wage sheet approved.' : 'Wage sheet rejected.');
    }

    public function overtime(Request $request): View
    {
        abort_unless($this->desk->canViewOvertime($request->user()), 403);
        $user = $request->user();

        return view('labor.overtime', [
            'rows' => LaborOvertime::query()->visibleTo($user)->with('employee')->latest('overtime_date')->limit(50)->get(),
            'employees' => LaborEmployee::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeOvertime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'overtime_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->enterOvertime($request->user(), $employee, $data);

        return back()->with('status', 'Overtime entered.');
    }

    public function decideOvertime(Request $request, LaborOvertime $labor_overtime): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveOvertime($request->user(), $labor_overtime, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Overtime approved.' : 'Overtime rejected.');
    }

    public function advances(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canRequestAdvance($user) || $this->desk->canApproveAdvance($user) || $user->isSuperAdmin(), 403);

        return view('labor.advances', [
            'rows' => LaborAdvance::query()->visibleTo($user)->with('employee')->latest()->limit(50)->get(),
            'employees' => $this->desk->employeesVisibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->requestAdvance($request->user(), $employee, $data);

        return back()->with('status', 'Advance requested.');
    }

    public function decideAdvance(Request $request, LaborAdvance $labor_advance): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveAdvance($request->user(), $labor_advance, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Advance approved.' : 'Advance rejected.');
    }

    public function payAdvance(LaborAdvance $labor_advance): RedirectResponse
    {
        $this->desk->markAdvancePaid(request()->user(), $labor_advance);

        return back()->with('status', 'Advance marked paid.');
    }

    public function payroll(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canExecutePayroll($user) || $this->desk->canApprovePayroll($user), 403);

        return view('labor.payroll', [
            'runs' => LaborPayrollRun::query()->visibleTo($user)->latest()->limit(40)->get(),
            'sheets' => LaborWageSheet::query()->visibleTo($user)->where('status', 'approved')->latest()->limit(40)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storePayroll(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['labor_wage_sheet_id' => ['required', 'integer']]);
        $sheet = LaborWageSheet::query()->findOrFail($data['labor_wage_sheet_id']);
        $this->desk->executePayroll($request->user(), $branch, $sheet);

        return back()->with('status', 'Payroll run created.');
    }

    public function decidePayroll(Request $request, LaborPayrollRun $labor_payroll_run): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approvePayroll($request->user(), $labor_payroll_run, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Payroll finalized.' : 'Payroll rejected.');
    }

    public function ppe(Request $request): View
    {
        abort_unless($this->desk->canLogPpe($request->user()), 403);
        $user = $request->user();

        return view('labor.ppe', [
            'logs' => \App\Models\Csms\LaborPpeLog::query()->visibleTo($user)->with('employee')->latest('logged_on')->limit(50)->get(),
            'employees' => $this->desk->employeesVisibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storePpe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'item_or_training' => ['required', 'string', 'max:255'],
            'log_type' => ['nullable', 'in:ppe,training'],
            'logged_on' => ['nullable', 'date'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'expires_on' => ['nullable', 'date'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->logPpe($request->user(), $employee, $data);

        return back()->with('status', 'PPE/training logged.');
    }

    public function leave(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canRequestLeave($user) || $this->desk->canApproveLeave($user), 403);

        return view('labor.leave', [
            'rows' => LaborLeaveRequest::query()->visibleTo($user)->with('employee')->latest()->limit(50)->get(),
            'employees' => LaborEmployee::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'labor_employee_id' => ['required', 'integer'],
            'leave_type' => ['nullable', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $employee = LaborEmployee::query()->findOrFail($data['labor_employee_id']);
        $this->desk->requestLeave($request->user(), $employee, $data);

        return back()->with('status', 'Leave requested.');
    }

    public function decideLeave(Request $request, LaborLeaveRequest $labor_leave_request): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->decideLeave($request->user(), $labor_leave_request, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Leave approved.' : 'Leave rejected.');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
