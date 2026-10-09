<?php

namespace App\Services\Monitoring;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\MonitoringAlarm;
use App\Models\Csms\MonitoringCalibration;
use App\Models\Csms\MonitoringConfigChange;
use App\Models\Csms\MonitoringDefrostLog;
use App\Models\Csms\MonitoringReadingAnnotation;
use App\Models\Csms\MonitoringSensor;
use App\Models\Csms\SensorReading;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MonitoringDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
    ) {}

    public function canViewDashboard(User $user): bool
    {
        return $user->hasPermission('monitoring.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canViewHistory(User $user): bool
    {
        return $user->hasPermission('monitoring.view');
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('monitoring.print');
    }

    public function canCreateSensor(User $user): bool
    {
        return $user->hasPermission('monitoring.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canConfigureSensor(User $user): bool
    {
        return ($user->isSuperAdmin() && $user->hasPermission('monitoring.create'))
            || ($user->hasPermission('monitoring.edit') && $user->hasAnyRole([RoleName::Admin, RoleName::Supervisor]));
    }

    public function canProposeThresholds(User $user): bool
    {
        return $user->hasPermission('monitoring.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canApproveConfig(User $user): bool
    {
        return $user->hasPermission('monitoring.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canAcknowledge(User $user): bool
    {
        return $user->hasPermission('monitoring.acknowledge')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canResolve(User $user): bool
    {
        return $user->hasPermission('monitoring.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canEscalate(User $user): bool
    {
        return $this->canResolve($user);
    }

    public function canManualLog(User $user): bool
    {
        return $user->hasPermission('monitoring.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canDefrost(User $user): bool
    {
        return $user->hasPermission('monitoring.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canRequestSuppress(User $user): bool
    {
        return $user->hasPermission('monitoring.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Supervisor));
    }

    public function canApproveSuppress(User $user): bool
    {
        return $user->hasPermission('monitoring.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canCalibrate(User $user): bool
    {
        return $user->hasPermission('monitoring.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canViewCalibration(User $user): bool
    {
        return $this->canCalibrate($user)
            || ($user->hasPermission('monitoring.view') && $user->hasRole(RoleName::Manager));
    }

    public function canCreateIncident(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $user->hasPermission('monitoring.view');
        }

        return $user->hasPermission('monitoring.create')
            && $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]);
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function branchOf(int $branchId): Branch
    {
        return Branch::query()->findOrFail($branchId);
    }

    public function assertBranchAccess(User $actor, int $branchId): Branch
    {
        $branch = Branch::query()->find($branchId);
        abort_unless($branch && $this->branches->allows($actor, $branch), 403);

        return $branch;
    }

    public function createSensor(User $actor, Chamber $chamber, array $data): MonitoringSensor
    {
        abort_unless($this->canCreateSensor($actor), 403);
        $this->assertBranchAccess($actor, (int) $chamber->branch_id);

        $sensor = MonitoringSensor::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'temperature',
            'status' => $data['status'] ?? 'offline',
            'config' => $data['config'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.sensor_created', $sensor, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => ['code' => $sensor->code, 'status' => $sensor->status],
        ]);

        return $sensor;
    }

    public function updateSensor(User $actor, MonitoringSensor $sensor, array $data): MonitoringSensor
    {
        abort_unless($this->canConfigureSensor($actor), 403);
        $this->assertBranchAccess($actor, (int) $sensor->branch_id);
        $old = $sensor->only(['name', 'type', 'status', 'config']);

        $sensor->update([
            'name' => $data['name'] ?? $sensor->name,
            'type' => $data['type'] ?? $sensor->type,
            'status' => $data['status'] ?? $sensor->status,
            'config' => array_key_exists('config', $data) ? $data['config'] : $sensor->config,
        ]);

        $this->audit->record('monitoring.sensor_updated', $sensor, [], $sensor->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $sensor->branch_id,
            'old' => $old,
            'new' => $sensor->only(['name', 'type', 'status', 'config']),
        ]);

        return $sensor->fresh();
    }

    public function proposeConfig(User $actor, Chamber $chamber, array $data, string $kind = 'thresholds'): MonitoringConfigChange
    {
        abort_unless($this->canProposeThresholds($actor), 403);
        $this->assertBranchAccess($actor, (int) $chamber->branch_id);

        $change = MonitoringConfigChange::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'kind' => $kind,
            'alarm_temp_min' => $data['alarm_temp_min'] ?? null,
            'alarm_temp_max' => $data['alarm_temp_max'] ?? null,
            'alarm_humidity_min' => $data['alarm_humidity_min'] ?? null,
            'alarm_humidity_max' => $data['alarm_humidity_max'] ?? null,
            'escalation_minutes' => $data['escalation_minutes'] ?? null,
            'escalation_role' => $data['escalation_role'] ?? null,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.config_proposed', $change, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => $change->only([
                'kind', 'alarm_temp_min', 'alarm_temp_max', 'alarm_humidity_min',
                'alarm_humidity_max', 'escalation_minutes', 'escalation_role',
            ]),
        ]);

        return $change;
    }

    public function approveConfig(User $actor, MonitoringConfigChange $change, bool $approve = true): void
    {
        abort_unless($this->canApproveConfig($actor), 403);
        abort_unless($change->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $change->created_by);
        $this->assertBranchAccess($actor, (int) $change->branch_id);

        if (! $approve) {
            $change->update(['status' => 'rejected', 'approved_by' => $actor->id]);
            $this->audit->record('monitoring.config_rejected', $change, [], $change->tenant_id, [
                'module' => 'monitoring',
                'branch_id' => $change->branch_id,
            ]);

            return;
        }

        $chamber = $change->chamber;
        $old = $chamber->only([
            'alarm_temp_min', 'alarm_temp_max', 'alarm_humidity_min', 'alarm_humidity_max',
            'escalation_minutes', 'escalation_role',
        ]);

        $chamber->update([
            'alarm_temp_min' => $change->alarm_temp_min,
            'alarm_temp_max' => $change->alarm_temp_max,
            'alarm_humidity_min' => $change->alarm_humidity_min,
            'alarm_humidity_max' => $change->alarm_humidity_max,
            'escalation_minutes' => $change->escalation_minutes,
            'escalation_role' => $change->escalation_role,
        ]);
        $change->update(['status' => 'approved', 'approved_by' => $actor->id]);

        $this->audit->record('monitoring.config_approved', $change, [], $change->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $change->branch_id,
            'old' => $old,
            'new' => $chamber->fresh()->only(array_keys($old)),
        ]);
    }

    public function recordManualLog(User $actor, Chamber $chamber, float $temperature, ?float $humidity, ?int $sensorId = null, ?string $notes = null): SensorReading
    {
        abort_unless($this->canManualLog($actor), 403);
        $this->assertBranchAccess($actor, (int) $chamber->branch_id);

        $sensor = null;
        if ($sensorId !== null) {
            $sensor = MonitoringSensor::query()->visibleTo($actor)->whereKey($sensorId)->firstOrFail();
            abort_unless((int) $sensor->chamber_id === (int) $chamber->id, 422);
        }

        $reading = SensorReading::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'sensor_id' => $sensor?->id,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'source' => 'manual',
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('sensor.recorded', $reading, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => ['temperature' => $temperature, 'humidity' => $humidity, 'source' => 'manual'],
        ]);

        $this->evaluateThresholds($actor, $chamber, $reading, $sensor);

        return $reading;
    }

    public function annotateReading(User $actor, SensorReading $reading, string $note): MonitoringReadingAnnotation
    {
        abort_unless($this->canManualLog($actor) || $this->canConfigureSensor($actor), 403);
        $this->assertBranchAccess($actor, (int) $reading->branch_id);

        $annotation = MonitoringReadingAnnotation::query()->create([
            'tenant_id' => $reading->tenant_id,
            'sensor_reading_id' => $reading->id,
            'note' => $note,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.reading_annotated', $annotation, [], $reading->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $reading->branch_id,
            'reason' => $note,
        ]);

        return $annotation;
    }

    public function evaluateThresholds(User $actor, Chamber $chamber, SensorReading $reading, ?MonitoringSensor $sensor = null): ?MonitoringAlarm
    {
        $suppressed = MonitoringAlarm::query()
            ->where('chamber_id', $chamber->id)
            ->where('status', 'suppressed')
            ->where('suppressed_until', '>', now())
            ->exists();

        if ($suppressed) {
            return null;
        }

        $breaches = [];
        if ($chamber->alarm_temp_min !== null && (float) $reading->temperature < (float) $chamber->alarm_temp_min) {
            $breaches[] = 'temperature below '.$chamber->alarm_temp_min;
        }
        if ($chamber->alarm_temp_max !== null && (float) $reading->temperature > (float) $chamber->alarm_temp_max) {
            $breaches[] = 'temperature above '.$chamber->alarm_temp_max;
        }
        if ($reading->humidity !== null && $chamber->alarm_humidity_min !== null && (float) $reading->humidity < (float) $chamber->alarm_humidity_min) {
            $breaches[] = 'humidity below '.$chamber->alarm_humidity_min;
        }
        if ($reading->humidity !== null && $chamber->alarm_humidity_max !== null && (float) $reading->humidity > (float) $chamber->alarm_humidity_max) {
            $breaches[] = 'humidity above '.$chamber->alarm_humidity_max;
        }

        if ($breaches === []) {
            return null;
        }

        $alarm = MonitoringAlarm::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'sensor_id' => $sensor?->id ?? $reading->sensor_id,
            'sensor_reading_id' => $reading->id,
            'type' => 'threshold',
            'severity' => 'warning',
            'message' => 'Threshold breach: '.implode('; ', $breaches),
            'reading_temperature' => $reading->temperature,
            'reading_humidity' => $reading->humidity,
            'status' => 'open',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.alarm_opened', $alarm, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => ['message' => $alarm->message],
        ]);

        if ($actor->isSuperAdmin() || $actor->hasRole(RoleName::Admin)) {
            $this->openIncident($actor, $alarm, 'Auto-created from threshold alarm.');
        }

        return $alarm;
    }

    public function acknowledge(User $actor, MonitoringAlarm $alarm): void
    {
        abort_unless($this->canAcknowledge($actor), 403);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);
        abort_unless(in_array($alarm->status, ['open', 'escalated'], true), 422);

        $alarm->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => now(),
        ]);

        $this->audit->record('monitoring.acknowledged', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
        ]);
    }

    public function resolve(User $actor, MonitoringAlarm $alarm, string $comment): void
    {
        abort_unless($this->canResolve($actor), 403);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);
        abort_unless(! in_array($alarm->status, ['resolved'], true), 422);

        $alarm->update([
            'status' => 'resolved',
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_comment' => $comment,
        ]);

        $this->audit->record('monitoring.alarm_resolved', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
            'reason' => $comment,
        ]);
    }

    public function escalate(User $actor, MonitoringAlarm $alarm): void
    {
        abort_unless($this->canEscalate($actor), 403);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);
        abort_unless(! in_array($alarm->status, ['resolved', 'suppressed'], true), 422);

        $alarm->update([
            'status' => 'escalated',
            'escalated_by' => $actor->id,
            'escalated_at' => now(),
            'escalation_level' => (int) $alarm->escalation_level + 1,
        ]);

        $this->audit->record('monitoring.alarm_escalated', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
            'new' => ['escalation_level' => $alarm->escalation_level],
        ]);
    }

    public function requestSuppression(User $actor, MonitoringAlarm $alarm, Carbon $until, string $reason): void
    {
        abort_unless($this->canRequestSuppress($actor) || $this->canApproveSuppress($actor), 403);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);

        if ($this->canApproveSuppress($actor) && ! $actor->hasRole(RoleName::Supervisor)) {
            $this->applySuppression($actor, $alarm, $until, $reason, $actor);

            return;
        }

        abort_unless($this->canRequestSuppress($actor), 403);

        $alarm->update([
            'suppression_status' => 'pending',
            'suppression_reason' => $reason,
            'suppressed_until' => $until,
            'suppression_requested_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.suppression_requested', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
            'reason' => $reason,
            'new' => ['suppressed_until' => $until->toIso8601String()],
        ]);
    }

    public function approveSuppression(User $actor, MonitoringAlarm $alarm, bool $approve = true): void
    {
        abort_unless($this->canApproveSuppress($actor), 403);
        abort_unless($alarm->suppression_status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $alarm->suppression_requested_by);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);

        if (! $approve) {
            $alarm->update([
                'suppression_status' => 'rejected',
                'suppression_approved_by' => $actor->id,
                'suppressed_until' => null,
            ]);
            $this->audit->record('monitoring.suppression_rejected', $alarm, [], $alarm->tenant_id, [
                'module' => 'monitoring',
                'branch_id' => $alarm->branch_id,
            ]);

            return;
        }

        $this->applySuppression(
            $actor,
            $alarm,
            $alarm->suppressed_until ?? now()->addHours(4),
            $alarm->suppression_reason ?? 'Approved maintenance window',
            $actor,
        );
    }

    private function applySuppression(User $actor, MonitoringAlarm $alarm, Carbon $until, string $reason, User $approver): void
    {
        if ($until->isPast()) {
            throw ValidationException::withMessages(['until' => 'Suppression window must end in the future.']);
        }

        $alarm->update([
            'status' => 'suppressed',
            'suppressed_until' => $until,
            'suppression_reason' => $reason,
            'suppression_status' => 'approved',
            'suppression_approved_by' => $approver->id,
            'suppression_requested_by' => $alarm->suppression_requested_by ?? $actor->id,
        ]);

        $this->audit->record('monitoring.alarm_suppressed', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
            'reason' => $reason,
            'new' => ['suppressed_until' => $until->toIso8601String()],
        ]);
    }

    public function logDefrost(User $actor, Chamber $chamber, Carbon $startedAt, ?Carbon $endedAt, ?string $notes): MonitoringDefrostLog
    {
        abort_unless($this->canDefrost($actor), 403);
        $this->assertBranchAccess($actor, (int) $chamber->branch_id);

        $log = MonitoringDefrostLog::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);

        if ($chamber->status !== 'defrost') {
            $chamber->update(['status' => 'defrost']);
        }

        $this->audit->record('monitoring.defrost_logged', $log, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => ['started_at' => $startedAt->toIso8601String()],
        ]);

        return $log;
    }

    public function recordCalibration(User $actor, MonitoringSensor $sensor, array $data): MonitoringCalibration
    {
        abort_unless($this->canCalibrate($actor), 403);
        $this->assertBranchAccess($actor, (int) $sensor->branch_id);

        $calibration = MonitoringCalibration::query()->create([
            'tenant_id' => $sensor->tenant_id,
            'branch_id' => $sensor->branch_id,
            'sensor_id' => $sensor->id,
            'calibrated_at' => $data['calibrated_at'],
            'reference_value' => $data['reference_value'],
            'measured_value' => $data['measured_value'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('monitoring.calibration_recorded', $calibration, [], $sensor->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $sensor->branch_id,
            'new' => [
                'reference_value' => $calibration->reference_value,
                'measured_value' => $calibration->measured_value,
            ],
        ]);

        return $calibration;
    }

    public function openIncident(User $actor, MonitoringAlarm $alarm, ?string $notes = null): MonitoringAlarm
    {
        abort_unless($this->canCreateIncident($actor), 403);
        $this->assertBranchAccess($actor, (int) $alarm->branch_id);

        if ($alarm->has_incident) {
            return $alarm;
        }

        $number = 'INC-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        $alarm->update([
            'has_incident' => true,
            'incident_number' => $number,
            'incident_notes' => $notes,
            'incident_created_by' => $actor->id,
            'incident_created_at' => now(),
        ]);

        $this->audit->record('monitoring.incident_opened', $alarm, [], $alarm->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $alarm->branch_id,
            'new' => ['incident_number' => $number],
            'reason' => $notes,
        ]);

        return $alarm->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dashboardRows(User $user): array
    {
        $chambers = Chamber::query()->visibleTo($user)->with(['sensors', 'branch'])->orderBy('code')->get();

        return $chambers->map(function (Chamber $chamber) use ($user) {
            $latest = SensorReading::query()->visibleTo($user)
                ->where('chamber_id', $chamber->id)
                ->latest('id')
                ->first();
            $activeAlarms = MonitoringAlarm::query()->visibleTo($user)
                ->where('chamber_id', $chamber->id)
                ->whereIn('status', ['open', 'acknowledged', 'escalated'])
                ->count();
            $sensors = $chamber->sensors->map(fn (MonitoringSensor $sensor) => [
                'id' => $sensor->id,
                'code' => $sensor->code,
                'name' => $sensor->name,
                'status' => $sensor->isLiveAvailable() ? 'active' : ($sensor->status ?: 'offline'),
                'live' => $sensor->isLiveAvailable(),
            ])->all();

            return [
                'chamber' => $chamber,
                'latest' => $latest,
                'sensors' => $sensors,
                'active_alarms' => $activeAlarms,
                'targets' => [
                    'set_point' => $chamber->set_point,
                    'humidity_set_point' => $chamber->humidity_set_point,
                    'alarm_temp_min' => $chamber->alarm_temp_min,
                    'alarm_temp_max' => $chamber->alarm_temp_max,
                ],
            ];
        })->all();
    }
}
