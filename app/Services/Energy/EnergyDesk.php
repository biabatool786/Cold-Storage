<?php

namespace App\Services\Energy;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\ElectricityMeter;
use App\Models\Csms\ElectricityMeterReading;
use App\Models\Csms\EnergyCostAllocation;
use App\Models\Csms\Generator;
use App\Models\Csms\GeneratorLog;
use App\Models\Csms\MeterChamberMapping;
use App\Models\Csms\PeakLoadAlert;
use App\Models\Csms\PeakLoadThreshold;
use App\Models\Csms\PowerEvent;
use App\Models\Csms\BillingAdjustment;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Billing\BillingDesk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnergyDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
        private readonly BillingDesk $billing,
    ) {}

    public function canEnterReading(User $user): bool
    {
        return $user->hasPermission('energy.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canEnterGeneratorLog(User $user): bool
    {
        return $this->canEnterReading($user);
    }

    public function canLogPowerEvent(User $user): bool
    {
        return $user->hasPermission('energy.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canMapMeters(User $user): bool
    {
        return $user->hasPermission('energy.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canViewReports(User $user): bool
    {
        return $user->hasPermission('energy.view');
    }

    public function canPrintReports(User $user): bool
    {
        return $user->hasPermission('energy.print')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEditPeakThreshold(User $user): bool
    {
        return $user->hasPermission('energy.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canApprovePeakThreshold(User $user): bool
    {
        return $user->hasPermission('energy.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canProposeAllocation(User $user): bool
    {
        return $user->hasPermission('energy.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveAllocation(User $user): bool
    {
        return $user->hasPermission('energy.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canViewPeakAlerts(User $user): bool
    {
        // Matrix: peak-load set = E (SA/Admin) or A (Manager) — not Supervisor/Accountant/Gatekeeper.
        return $this->canEditPeakThreshold($user) || $this->canApprovePeakThreshold($user);
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function ensureMeter(User $actor, Branch $branch, array $data): ElectricityMeter
    {
        abort_unless($this->canEnterReading($actor) || $this->canMapMeters($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $code = strtoupper(trim($data['meter_code']));
        $existing = ElectricityMeter::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('meter_code', $code)
            ->first();
        if ($existing) {
            return $existing;
        }

        $meter = ElectricityMeter::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'meter_code' => $code,
            'name' => $data['name'] ?? $code,
            'location' => $data['location'] ?? null,
            'last_reading' => 0,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('energy.meter_created', $meter, [], $branch->tenant_id, [
            'module' => 'energy', 'branch_id' => $branch->id,
        ]);

        return $meter;
    }

    public function recordReading(User $actor, ElectricityMeter $meter, array $data): ElectricityMeterReading
    {
        abort_unless($this->canEnterReading($actor), 403);
        $this->assertBranchAccess($actor, (int) $meter->branch_id);

        $current = (float) $data['current_reading'];
        $previous = (float) ($data['previous_reading'] ?? $meter->last_reading);
        $isReset = (bool) ($data['is_reset'] ?? false);
        $resetReason = $data['reset_reason'] ?? null;

        if ($current < 0 || $previous < 0) {
            throw ValidationException::withMessages(['current_reading' => 'Readings must be non-negative.']);
        }
        if (! $isReset && $current < $previous) {
            throw ValidationException::withMessages([
                'current_reading' => 'Current reading is lower than previous. Use an explicit meter reset.',
            ]);
        }
        if ($isReset && blank($resetReason)) {
            throw ValidationException::withMessages(['reset_reason' => 'Reset reason is required.']);
        }

        $consumption = $isReset ? $current : round($current - $previous, 3);
        $readAt = Carbon::parse($data['read_at'] ?? now());

        $reading = DB::transaction(function () use ($actor, $meter, $readAt, $previous, $current, $consumption, $isReset, $resetReason, $data) {
            $row = ElectricityMeterReading::query()->create([
                'tenant_id' => $meter->tenant_id,
                'branch_id' => $meter->branch_id,
                'electricity_meter_id' => $meter->id,
                'read_at' => $readAt,
                'previous_reading' => $previous,
                'current_reading' => $current,
                'consumption' => $consumption,
                'is_reset' => $isReset,
                'reset_reason' => $isReset ? $resetReason : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $meter->update([
                'last_reading' => $current,
                'last_reading_at' => $readAt,
            ]);
            $this->evaluatePeakAlert($meter, $row, $consumption);

            return $row;
        });

        $this->audit->record('energy.reading_recorded', $reading, [], $meter->tenant_id, [
            'module' => 'energy', 'branch_id' => $meter->branch_id,
            'new' => ['consumption' => $consumption, 'is_reset' => $isReset],
            'reason' => $isReset ? $resetReason : null,
        ]);

        return $reading;
    }

    public function ensureGenerator(User $actor, Branch $branch, array $data): Generator
    {
        abort_unless($this->canEnterGeneratorLog($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $code = strtoupper(trim($data['generator_code']));
        $existing = Generator::query()
            ->where('tenant_id', $branch->tenant_id)
            ->where('generator_code', $code)
            ->first();
        if ($existing) {
            return $existing;
        }

        return Generator::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'generator_code' => $code,
            'name' => $data['name'] ?? $code,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
    }

    public function recordGeneratorLog(User $actor, Generator $generator, array $data): GeneratorLog
    {
        abort_unless($this->canEnterGeneratorLog($actor), 403);
        $this->assertBranchAccess($actor, (int) $generator->branch_id);

        $runtime = (float) $data['runtime_hours'];
        $fuel = (float) $data['fuel_quantity'];
        if ($runtime < 0 || $fuel < 0) {
            throw ValidationException::withMessages(['runtime_hours' => 'Values must be non-negative.']);
        }

        $flags = [];
        if ($runtime > 24) {
            $flags[] = 'Runtime exceeds 24 hours in a single log.';
        }
        if ($runtime > 0 && $fuel / $runtime > 100) {
            $flags[] = 'Fuel rate looks unusually high.';
        }
        if ($runtime === 0.0 && $fuel > 0) {
            $flags[] = 'Fuel recorded with zero runtime.';
        }
        $flagged = $flags !== [];
        $flagReason = $flagged ? implode(' ', $flags) : null;

        $log = GeneratorLog::query()->create([
            'tenant_id' => $generator->tenant_id,
            'branch_id' => $generator->branch_id,
            'generator_id' => $generator->id,
            'logged_at' => $data['logged_at'] ?? now(),
            'runtime_hours' => $runtime,
            'fuel_quantity' => $fuel,
            'fuel_unit' => $data['fuel_unit'] ?? 'liters',
            'flagged' => $flagged,
            'flag_reason' => $flagReason,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('energy.generator_log_recorded', $log, [], $generator->tenant_id, [
            'module' => 'energy', 'branch_id' => $generator->branch_id,
        ]);

        return $log;
    }

    public function logPowerEvent(User $actor, Branch $branch, array $data): PowerEvent
    {
        abort_unless($this->canLogPowerEvent($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        abort_unless(in_array($data['event_type'], ['power_cut', 'backup_switch'], true), 422);

        $started = Carbon::parse($data['started_at']);
        $ended = ! empty($data['ended_at']) ? Carbon::parse($data['ended_at']) : null;
        if ($ended && $ended->lt($started)) {
            throw ValidationException::withMessages(['ended_at' => 'End time must be after start time.']);
        }

        if ($data['event_type'] === 'power_cut') {
            $open = PowerEvent::query()
                ->where('branch_id', $branch->id)
                ->where('event_type', 'power_cut')
                ->where('status', 'open')
                ->exists();
            if ($open && ! $ended) {
                throw ValidationException::withMessages(['started_at' => 'An open power-cut already exists for this branch.']);
            }
        }

        $chamberId = $data['chamber_id'] ?? null;
        if ($chamberId) {
            $chamber = Chamber::query()->findOrFail($chamberId);
            abort_unless((int) $chamber->branch_id === (int) $branch->id, 422);
            abort_unless((int) $chamber->tenant_id === (int) $branch->tenant_id, 422);
        }

        $event = PowerEvent::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'chamber_id' => $chamberId,
            'event_type' => $data['event_type'],
            'started_at' => $started,
            'ended_at' => $ended,
            'backup_source' => $data['backup_source'] ?? null,
            'status' => $ended ? 'closed' : 'open',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('energy.power_event_logged', $event, [], $branch->tenant_id, [
            'module' => 'energy', 'branch_id' => $branch->id,
        ]);

        return $event;
    }

    public function closePowerEvent(User $actor, PowerEvent $event, string $endedAt): void
    {
        abort_unless($this->canLogPowerEvent($actor), 403);
        $this->assertBranchAccess($actor, (int) $event->branch_id);
        abort_unless($event->status === 'open', 422);
        $ended = Carbon::parse($endedAt);
        if ($ended->lt($event->started_at)) {
            throw ValidationException::withMessages(['ended_at' => 'End time must be after start time.']);
        }
        $event->update(['ended_at' => $ended, 'status' => 'closed']);
        $this->audit->record('energy.power_event_closed', $event, [], $event->tenant_id, [
            'module' => 'energy', 'branch_id' => $event->branch_id,
        ]);
    }

    public function mapMeterToChamber(User $actor, ElectricityMeter $meter, Chamber $chamber, array $data): MeterChamberMapping
    {
        abort_unless($this->canMapMeters($actor), 403);
        $this->assertBranchAccess($actor, (int) $meter->branch_id);
        abort_unless((int) $chamber->tenant_id === (int) $meter->tenant_id, 422);
        abort_unless((int) $chamber->branch_id === (int) $meter->branch_id, 422);

        $from = Carbon::parse($data['effective_from'] ?? now())->toDateString();

        return DB::transaction(function () use ($actor, $meter, $chamber, $from, $data) {
            MeterChamberMapping::query()
                ->where('electricity_meter_id', $meter->id)
                ->whereNull('effective_to')
                ->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]);

            $mapping = MeterChamberMapping::query()->create([
                'tenant_id' => $meter->tenant_id,
                'branch_id' => $meter->branch_id,
                'electricity_meter_id' => $meter->id,
                'chamber_id' => $chamber->id,
                'effective_from' => $from,
                'effective_to' => $data['effective_to'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->audit->record('energy.meter_chamber_mapped', $mapping, [], $meter->tenant_id, [
                'module' => 'energy', 'branch_id' => $meter->branch_id,
                'new' => ['chamber_id' => $chamber->id, 'effective_from' => $from],
            ]);

            return $mapping;
        });
    }

    /**
     * @return array{kwh: float|null, energy_cost: float|null, tonnage: float|null, cost_per_ton: float|string, sources: array<string, string>}
     */
    public function consumptionReport(User $actor, Branch $branch, ?Chamber $chamber, string $from, string $to, ?float $energyCost): array
    {
        abort_unless($this->canViewReports($actor), 403);
        $this->assertBranchAccess($actor, (int) $branch->id);

        $readings = ElectricityMeterReading::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('read_at', [$from.' 00:00:00', $to.' 23:59:59']);
        if ($chamber) {
            $meterIds = MeterChamberMapping::query()
                ->where('chamber_id', $chamber->id)
                ->where('effective_from', '<=', $to)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->pluck('electricity_meter_id');
            $readings->whereIn('electricity_meter_id', $meterIds);
        }
        $kwh = (float) $readings->sum('consumption');

        $lots = StorageLot::query()->where('branch_id', $branch->id);
        if ($chamber) {
            $lots->where('chamber_id', $chamber->id);
        }
        $qty = (float) $lots->sum('quantity');
        $tonnage = $qty > 0 ? round($qty / 1000, 3) : null;

        $cost = $energyCost;
        $sources = [
            'kwh' => 'Sum of electricity meter reading consumption for the period',
            'tonnage' => $tonnage !== null
                ? 'Storage lot quantity sum ÷ 1000 (assumes quantity stored in kg)'
                : 'No storage lot quantity available for branch/chamber',
            'energy_cost' => $cost !== null
                ? 'User-supplied energy cost for the reporting period'
                : 'Energy cost not provided',
        ];

        $costPerTon = 'Unavailable';
        if ($cost !== null && $tonnage !== null && $tonnage > 0) {
            $costPerTon = round($cost / $tonnage, 2);
        } elseif ($cost === null || $tonnage === null || $tonnage <= 0) {
            $costPerTon = 'Unavailable';
        }

        return [
            'kwh' => $kwh,
            'energy_cost' => $cost,
            'tonnage' => $tonnage,
            'cost_per_ton' => $costPerTon,
            'sources' => $sources,
        ];
    }

    public function proposePeakThreshold(User $actor, Branch $branch, array $data): PeakLoadThreshold
    {
        abort_unless($this->canEditPeakThreshold($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $threshold = PeakLoadThreshold::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'electricity_meter_id' => $data['electricity_meter_id'] ?? null,
            'threshold_kw' => $data['threshold_kw'],
            'status' => $actor->isSuperAdmin() ? 'approved' : 'pending',
            'proposed_by' => $actor->id,
            'approved_by' => $actor->isSuperAdmin() ? $actor->id : null,
            'approved_at' => $actor->isSuperAdmin() ? now() : null,
            'notes' => $data['notes'] ?? null,
        ]);
        $this->audit->record('energy.peak_threshold_proposed', $threshold, [], $branch->tenant_id, [
            'module' => 'energy', 'branch_id' => $branch->id,
        ]);

        return $threshold;
    }

    public function approvePeakThreshold(User $actor, PeakLoadThreshold $threshold, bool $approve): void
    {
        abort_unless($this->canApprovePeakThreshold($actor), 403);
        $this->assertBranchAccess($actor, (int) $threshold->branch_id);
        abort_unless($threshold->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $threshold->proposed_by);

        $threshold->update([
            'status' => $approve ? 'approved' : 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $this->audit->record($approve ? 'energy.peak_threshold_approved' : 'energy.peak_threshold_rejected', $threshold, [], $threshold->tenant_id, [
            'module' => 'energy', 'branch_id' => $threshold->branch_id,
        ]);
    }

    private function evaluatePeakAlert(ElectricityMeter $meter, ElectricityMeterReading $reading, float $measured): void
    {
        $threshold = PeakLoadThreshold::query()
            ->where('branch_id', $meter->branch_id)
            ->where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('electricity_meter_id')->orWhere('electricity_meter_id', $meter->id))
            ->latest('id')
            ->first();
        if (! $threshold || $measured <= (float) $threshold->threshold_kw) {
            return;
        }

        PeakLoadAlert::query()->create([
            'tenant_id' => $meter->tenant_id,
            'branch_id' => $meter->branch_id,
            'electricity_meter_id' => $meter->id,
            'peak_load_threshold_id' => $threshold->id,
            'electricity_meter_reading_id' => $reading->id,
            'alerted_at' => $reading->read_at,
            'measured_load' => $measured,
            'threshold_kw' => $threshold->threshold_kw,
            'status' => 'open',
            'source' => 'manual_reading',
        ]);
        $this->audit->record('energy.peak_alert_generated', $reading, [], $meter->tenant_id, [
            'module' => 'energy', 'branch_id' => $meter->branch_id,
            'new' => ['measured_load' => $measured, 'threshold_kw' => $threshold->threshold_kw],
        ]);
    }

    public function proposeAllocation(User $actor, Branch $branch, array $data): EnergyCostAllocation
    {
        abort_unless($this->canProposeAllocation($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $invoiceId = $data['storage_invoice_id'] ?? null;
        if ($invoiceId) {
            $invoice = StorageInvoice::query()->findOrFail($invoiceId);
            abort_unless((int) $invoice->tenant_id === (int) $branch->tenant_id, 422);
            abort_unless((int) $invoice->branch_id === (int) $branch->id, 422);
            abort_unless($invoice->isPosted(), 422, 'Only posted invoices can receive an energy debit note.');
        }

        $allocation = EnergyCostAllocation::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'energy_cost' => $data['energy_cost'],
            'kwh_total' => $data['kwh_total'] ?? null,
            'allocation_basis' => $data['allocation_basis'] ?? 'manual_proposal',
            'calculation_notes' => $data['calculation_notes']
                ?? 'Business formula for energy surcharge is not defined in the matrix; this is a configurable proposal only.',
            'status' => 'pending',
            'storage_invoice_id' => $invoiceId,
            'proposed_by' => $actor->id,
        ]);
        $this->audit->record('energy.allocation_proposed', $allocation, [], $branch->tenant_id, [
            'module' => 'energy', 'branch_id' => $branch->id,
        ]);

        return $allocation;
    }

    public function approveAllocation(User $actor, EnergyCostAllocation $allocation, bool $approve): void
    {
        abort_unless($this->canApproveAllocation($actor), 403);
        $this->assertBranchAccess($actor, (int) $allocation->branch_id);
        abort_unless($allocation->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $allocation->proposed_by);
        $this->limits->assertCanApprove($actor, 'energy_allocation', $allocation->energy_cost);

        if (! $approve) {
            $allocation->update(['status' => 'rejected', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record('energy.allocation_rejected', $allocation, [], $allocation->tenant_id, [
                'module' => 'energy', 'branch_id' => $allocation->branch_id,
            ]);

            return;
        }

        DB::transaction(function () use ($actor, $allocation): void {
            $invoiceId = $allocation->storage_invoice_id;
            $adjustmentId = null;
            if ($invoiceId) {
                $invoice = StorageInvoice::query()->findOrFail($invoiceId);
                abort_unless((int) $invoice->branch_id === (int) $allocation->branch_id, 422);
                abort_unless($invoice->isPosted(), 422);
                // Never mutate posted invoice lines — open a debit note for Manager billing approval.
                $adjustment = BillingAdjustment::query()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'branch_id' => $invoice->branch_id,
                    'storage_invoice_id' => $invoice->id,
                    'kind' => 'debit',
                    'number' => 'DN-NRG-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
                    'amount' => $allocation->energy_cost,
                    'reason' => 'Energy surcharge allocation #'.$allocation->id
                        .' ('.$allocation->period_start->toDateString().'–'.$allocation->period_end->toDateString().')',
                    'status' => 'pending',
                    'created_by' => $allocation->proposed_by,
                ]);
                if ($this->billing->canApproveAdjustment($actor)) {
                    $this->billing->approveAdjustment($actor, $adjustment, true);
                }
                $adjustmentId = $adjustment->id;
            }

            $allocation->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'billing_adjustment_id' => $adjustmentId,
            ]);
            $this->audit->record('energy.allocation_approved', $allocation, [], $allocation->tenant_id, [
                'module' => 'energy', 'branch_id' => $allocation->branch_id,
            ]);
        });
    }
}

