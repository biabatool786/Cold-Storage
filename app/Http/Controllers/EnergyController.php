<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\ElectricityMeter;
use App\Models\Csms\EnergyCostAllocation;
use App\Models\Csms\Generator;
use App\Models\Csms\PeakLoadThreshold;
use App\Models\Csms\PowerEvent;
use App\Models\Csms\StorageInvoice;
use App\Services\Energy\EnergyDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnergyController extends Controller
{
    public function __construct(private readonly EnergyDesk $desk) {}

    public function readings(Request $request): View
    {
        abort_unless($this->desk->canEnterReading($request->user()), 403);
        $user = $request->user();

        return view('energy.readings', [
            'readings' => \App\Models\Csms\ElectricityMeterReading::query()->visibleTo($user)->with('meter')->latest('read_at')->limit(80)->get(),
            'meters' => ElectricityMeter::query()->visibleTo($user)->orderBy('meter_code')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeMeter(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'meter_code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $this->desk->ensureMeter($request->user(), $branch, $data);

        return back()->with('status', 'Meter saved.');
    }

    public function storeReading(Request $request, ElectricityMeter $electricity_meter): RedirectResponse
    {
        $data = $request->validate([
            'current_reading' => ['required', 'numeric', 'min:0'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'read_at' => ['nullable', 'date'],
            'is_reset' => ['nullable', 'boolean'],
            'reset_reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['is_reset'] = $request->boolean('is_reset');
        $this->desk->recordReading($request->user(), $electricity_meter, $data);

        return back()->with('status', 'Reading recorded.');
    }

    public function generators(Request $request): View
    {
        abort_unless($this->desk->canEnterGeneratorLog($request->user()), 403);
        $user = $request->user();

        return view('energy.generators', [
            'logs' => \App\Models\Csms\GeneratorLog::query()->visibleTo($user)->with('generator')->latest('logged_at')->limit(80)->get(),
            'generators' => Generator::query()->visibleTo($user)->orderBy('generator_code')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeGenerator(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'generator_code' => ['required', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $this->desk->ensureGenerator($request->user(), $branch, $data);

        return back()->with('status', 'Generator saved.');
    }

    public function storeGeneratorLog(Request $request, Generator $generator): RedirectResponse
    {
        $data = $request->validate([
            'runtime_hours' => ['required', 'numeric', 'min:0'],
            'fuel_quantity' => ['required', 'numeric', 'min:0'],
            'fuel_unit' => ['nullable', 'string', 'max:20'],
            'logged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->recordGeneratorLog($request->user(), $generator, $data);

        return back()->with('status', 'Generator log recorded.');
    }

    public function powerEvents(Request $request): View
    {
        abort_unless($this->desk->canLogPowerEvent($request->user()), 403);
        $user = $request->user();

        return view('energy.power-events', [
            'events' => PowerEvent::query()->visibleTo($user)->with('chamber')->latest('started_at')->limit(80)->get(),
            'branches' => $this->branchesFor($user),
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storePowerEvent(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'in:power_cut,backup_switch'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date'],
            'chamber_id' => ['nullable', 'integer'],
            'backup_source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->logPowerEvent($request->user(), $branch, $data);

        return back()->with('status', 'Power event logged.');
    }

    public function closePowerEvent(Request $request, PowerEvent $power_event): RedirectResponse
    {
        $data = $request->validate(['ended_at' => ['required', 'date']]);
        $this->desk->closePowerEvent($request->user(), $power_event, $data['ended_at']);

        return back()->with('status', 'Power event closed.');
    }

    public function mappings(Request $request): View
    {
        abort_unless($this->desk->canMapMeters($request->user()), 403);
        $user = $request->user();

        return view('energy.mappings', [
            'mappings' => \App\Models\Csms\MeterChamberMapping::query()->visibleTo($user)->with(['meter', 'chamber'])->latest()->limit(80)->get(),
            'meters' => ElectricityMeter::query()->visibleTo($user)->orderBy('meter_code')->limit(100)->get(),
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeMapping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'electricity_meter_id' => ['required', 'integer'],
            'chamber_id' => ['required', 'integer'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $meter = ElectricityMeter::query()->findOrFail($data['electricity_meter_id']);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->desk->mapMeterToChamber($request->user(), $meter, $chamber, $data);

        return back()->with('status', 'Meter mapped to chamber.');
    }

    public function reports(Request $request): View
    {
        abort_unless($this->desk->canViewReports($request->user()), 403);
        $user = $request->user();
        $branches = $this->branchesFor($user);
        $branchId = $request->integer('branch_id') ?: ($branches->first()?->id);
        $branch = $branchId ? Branch::query()->findOrFail($branchId) : null;
        $chamber = $request->integer('chamber_id') ? Chamber::query()->findOrFail($request->integer('chamber_id')) : null;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $cost = $request->filled('energy_cost') ? (float) $request->input('energy_cost') : null;
        $report = $branch ? $this->desk->consumptionReport($user, $branch, $chamber, $from, $to, $cost) : null;

        return view('energy.reports', [
            'branches' => $branches,
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'branch' => $branch,
            'chamber' => $chamber,
            'from' => $from,
            'to' => $to,
            'energyCost' => $cost,
            'report' => $report,
            'desk' => $this->desk,
        ]);
    }

    public function printReport(Request $request, Branch $branch): View
    {
        abort_unless($this->desk->canPrintReports($request->user()), 403);
        $user = $request->user();
        $this->desk->assertBranchAccess($user, (int) $branch->id);
        $chamber = $request->integer('chamber_id')
            ? Chamber::query()->findOrFail($request->integer('chamber_id'))
            : null;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $cost = $request->filled('energy_cost') ? (float) $request->input('energy_cost') : null;

        return view('energy.report-print', [
            'branch' => $branch,
            'chamber' => $chamber,
            'from' => $from,
            'to' => $to,
            'report' => $this->desk->consumptionReport($user, $branch, $chamber, $from, $to, $cost),
        ]);
    }

    public function peakAlerts(Request $request): View
    {
        abort_unless($this->desk->canViewPeakAlerts($request->user()), 403);
        $user = $request->user();

        return view('energy.peak-alerts', [
            'thresholds' => PeakLoadThreshold::query()->visibleTo($user)->with('meter')->latest()->limit(50)->get(),
            'alerts' => \App\Models\Csms\PeakLoadAlert::query()->visibleTo($user)->with('meter')->latest('alerted_at')->limit(50)->get(),
            'meters' => ElectricityMeter::query()->visibleTo($user)->orderBy('meter_code')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storePeakThreshold(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'threshold_kw' => ['required', 'numeric', 'min:0.001'],
            'electricity_meter_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->proposePeakThreshold($request->user(), $branch, $data);

        return back()->with('status', 'Peak-load threshold submitted.');
    }

    public function decidePeakThreshold(Request $request, PeakLoadThreshold $peak_load_threshold): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approvePeakThreshold($request->user(), $peak_load_threshold, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Threshold approved.' : 'Threshold rejected.');
    }

    public function allocations(Request $request): View
    {
        abort_unless($this->desk->canProposeAllocation($request->user()) || $this->desk->canApproveAllocation($request->user()), 403);
        $user = $request->user();

        return view('energy.allocations', [
            'allocations' => EnergyCostAllocation::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'invoices' => StorageInvoice::query()->visibleTo($user)->whereIn('status', ['posted', 'locked'])->latest()->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeAllocation(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'energy_cost' => ['required', 'numeric', 'min:0.01'],
            'kwh_total' => ['nullable', 'numeric', 'min:0'],
            'storage_invoice_id' => ['nullable', 'integer'],
            'calculation_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->desk->proposeAllocation($request->user(), $branch, $data);

        return back()->with('status', 'Energy allocation proposed.');
    }

    public function decideAllocation(Request $request, EnergyCostAllocation $energy_cost_allocation): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveAllocation($request->user(), $energy_cost_allocation, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Allocation approved.' : 'Allocation rejected.');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
