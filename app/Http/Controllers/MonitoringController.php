<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\MonitoringAlarm;
use App\Models\Csms\MonitoringCalibration;
use App\Models\Csms\MonitoringConfigChange;
use App\Models\Csms\MonitoringDefrostLog;
use App\Models\Csms\MonitoringSensor;
use App\Models\Csms\SensorReading;
use App\Services\Monitoring\MonitoringDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __construct(private readonly MonitoringDesk $desk) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $this->desk->canViewDashboard($user)) {
            abort_unless($this->desk->canViewHistory($user), 403);

            return redirect()->route('monitoring.history');
        }

        return view('monitoring.dashboard', [
            'rows' => $this->desk->dashboardRows($user),
            'desk' => $this->desk,
            'pendingConfigs' => MonitoringConfigChange::query()->visibleTo($user)->where('status', 'pending')->with('chamber')->latest()->limit(20)->get(),
            'alarms' => MonitoringAlarm::query()->visibleTo($user)->whereIn('status', ['open', 'acknowledged', 'escalated'])->with('chamber')->latest()->limit(10)->get(),
        ]);
    }

    public function history(Request $request): View
    {
        abort_unless($this->desk->canViewHistory($request->user()), 403);
        $user = $request->user();

        return view('monitoring.history', [
            'readings' => SensorReading::query()->visibleTo($user)->with(['chamber', 'sensor'])->latest('id')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function sensors(Request $request): View
    {
        abort_unless($this->desk->canViewDashboard($request->user()) || $this->desk->canConfigureSensor($request->user()) || $this->desk->canCreateSensor($request->user()), 403);
        $user = $request->user();

        return view('monitoring.sensors', [
            'sensors' => MonitoringSensor::query()->visibleTo($user)->with('chamber')->orderBy('code')->get(),
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('code')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeSensor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chamber_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:temperature,humidity,both'],
            'status' => ['nullable', 'in:active,offline,maintenance,retired'],
        ]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->desk->createSensor($request->user(), $chamber, $data);

        return back()->with('status', 'Sensor configured.');
    }

    public function updateSensor(Request $request, MonitoringSensor $monitoring_sensor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:temperature,humidity,both'],
            'status' => ['required', 'in:active,offline,maintenance,retired'],
        ]);
        $this->desk->updateSensor($request->user(), $monitoring_sensor, $data);

        return back()->with('status', 'Sensor updated.');
    }

    public function proposeConfig(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:thresholds,escalation'],
            'alarm_temp_min' => ['nullable', 'numeric'],
            'alarm_temp_max' => ['nullable', 'numeric'],
            'alarm_humidity_min' => ['nullable', 'numeric'],
            'alarm_humidity_max' => ['nullable', 'numeric'],
            'escalation_minutes' => ['nullable', 'integer', 'min:1'],
            'escalation_role' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->proposeConfig($request->user(), $chamber, $data, $data['kind']);

        return back()->with('status', 'Configuration change submitted for approval.');
    }

    public function decideConfig(Request $request, MonitoringConfigChange $monitoring_config_change): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveConfig($request->user(), $monitoring_config_change, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Configuration approved.' : 'Configuration rejected.');
    }

    public function storeReading(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chamber_id' => ['required', 'integer'],
            'sensor_id' => ['nullable', 'integer'],
            'temperature' => ['required', 'numeric'],
            'humidity' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->desk->recordManualLog(
            $request->user(),
            $chamber,
            (float) $data['temperature'],
            isset($data['humidity']) ? (float) $data['humidity'] : null,
            isset($data['sensor_id']) ? (int) $data['sensor_id'] : null,
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Manual temperature log saved.');
    }

    public function annotate(Request $request, SensorReading $sensor_reading): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $this->desk->annotateReading($request->user(), $sensor_reading, $data['note']);

        return back()->with('status', 'Annotation recorded.');
    }

    public function alarms(Request $request): View
    {
        abort_unless($this->desk->canViewDashboard($request->user()) || $this->desk->canAcknowledge($request->user()), 403);
        $user = $request->user();

        return view('monitoring.alarms', [
            'alarms' => MonitoringAlarm::query()->visibleTo($user)->with(['chamber', 'sensor'])->latest()->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function showAlarm(MonitoringAlarm $monitoring_alarm): View
    {
        abort_unless($this->desk->canViewDashboard(request()->user()) || $this->desk->canAcknowledge(request()->user()), 403);
        $this->desk->assertBranchAccess(request()->user(), (int) $monitoring_alarm->branch_id);
        $monitoring_alarm->load(['chamber', 'sensor', 'reading']);

        return view('monitoring.alarm-show', ['alarm' => $monitoring_alarm, 'desk' => $this->desk]);
    }

    public function acknowledge(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $this->desk->acknowledge($request->user(), $monitoring_alarm);

        return back()->with('status', 'Alarm acknowledged.');
    }

    public function resolve(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->desk->resolve($request->user(), $monitoring_alarm, $data['comment']);

        return back()->with('status', 'Alarm resolved.');
    }

    public function escalate(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $this->desk->escalate($request->user(), $monitoring_alarm);

        return back()->with('status', 'Alarm escalated.');
    }

    public function requestSuppress(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $data = $request->validate([
            'until' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->desk->requestSuppression($request->user(), $monitoring_alarm, \Illuminate\Support\Carbon::parse($data['until']), $data['reason']);

        return back()->with('status', 'Suppression submitted.');
    }

    public function decideSuppress(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveSuppression($request->user(), $monitoring_alarm, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Suppression approved.' : 'Suppression rejected.');
    }

    public function openIncident(Request $request, MonitoringAlarm $monitoring_alarm): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $this->desk->openIncident($request->user(), $monitoring_alarm, $data['notes'] ?? null);

        return back()->with('status', 'Incident ticket opened.');
    }

    public function defrost(Request $request): View
    {
        abort_unless($this->desk->canDefrost($request->user()) || $this->desk->canViewDashboard($request->user()), 403);
        $user = $request->user();

        return view('monitoring.defrost', [
            'logs' => MonitoringDefrostLog::query()->visibleTo($user)->with('chamber')->latest()->limit(50)->get(),
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('code')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeDefrost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chamber_id' => ['required', 'integer'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $this->desk->logDefrost(
            $request->user(),
            $chamber,
            \Illuminate\Support\Carbon::parse($data['started_at']),
            isset($data['ended_at']) ? \Illuminate\Support\Carbon::parse($data['ended_at']) : null,
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Defrost cycle logged.');
    }

    public function calibrations(Request $request): View
    {
        abort_unless($this->desk->canViewCalibration($request->user()), 403);
        $user = $request->user();

        return view('monitoring.calibrations', [
            'calibrations' => MonitoringCalibration::query()->visibleTo($user)->with('sensor')->latest()->limit(50)->get(),
            'sensors' => MonitoringSensor::query()->visibleTo($user)->orderBy('code')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeCalibration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sensor_id' => ['required', 'integer'],
            'calibrated_at' => ['required', 'date'],
            'reference_value' => ['required', 'numeric'],
            'measured_value' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $sensor = MonitoringSensor::query()->findOrFail($data['sensor_id']);
        $this->desk->recordCalibration($request->user(), $sensor, [
            'calibrated_at' => $data['calibrated_at'],
            'reference_value' => $data['reference_value'],
            'measured_value' => $data['measured_value'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Calibration recorded.');
    }

    public function excursions(Request $request): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $user = $request->user();

        return view('monitoring.excursions', [
            'alarms' => MonitoringAlarm::query()->visibleTo($user)->where('type', 'threshold')->with('chamber')->latest()->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function printExcursion(Request $request): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $user = $request->user();

        return view('monitoring.print-excursion', [
            'alarms' => MonitoringAlarm::query()->visibleTo($user)->where('type', 'threshold')->with('chamber')->latest()->limit(100)->get(),
        ]);
    }

    public function incidents(Request $request): View
    {
        abort_unless($this->desk->canViewDashboard($request->user()) || $this->desk->canCreateIncident($request->user()), 403);
        $user = $request->user();

        return view('monitoring.incidents', [
            'incidents' => MonitoringAlarm::query()->visibleTo($user)->where('has_incident', true)->with('chamber')->latest('incident_created_at')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function manualLog(Request $request): View
    {
        abort_unless($this->desk->canManualLog($request->user()) || $this->desk->canViewHistory($request->user()), 403);
        $user = $request->user();

        return view('monitoring.manual-log', [
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('code')->get(),
            'sensors' => MonitoringSensor::query()->visibleTo($user)->orderBy('code')->get(),
            'desk' => $this->desk,
        ]);
    }
}
