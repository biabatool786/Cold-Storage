<?php

namespace App\Services\Weighbridge;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\GateEntry;
use App\Models\Csms\WeighbridgeCalibration;
use App\Models\Csms\WeighbridgeTolerance;
use App\Models\Csms\Weighment;
use App\Models\Csms\WeighmentEditRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use Illuminate\Support\Str;

class WeighbridgeDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
    ) {}

    public function canRecord(User $user): bool
    {
        return $user->hasPermission('weighbridge.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper));
    }

    public function canApprove(User $user): bool
    {
        return $user->hasPermission('weighbridge.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEditDirect(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('weighbridge.edit');
    }

    public function canProposeEdit(User $user): bool
    {
        return $user->hasPermission('weighbridge.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('weighbridge.delete');
    }

    public function canConfigure(User $user): bool
    {
        return $user->hasPermission('weighbridge.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canAcknowledge(User $user): bool
    {
        return $user->hasPermission('weighbridge.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('weighbridge.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canCalibrate(User $user): bool
    {
        return $this->canConfigure($user);
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function record(User $actor, GateEntry $entry, string $kind, float $weight, bool $manual = false, ?string $reason = null): Weighment
    {
        abort_unless($this->canRecord($actor), 403);
        $this->guard($actor, $entry->branch);
        abort_unless(in_array($kind, ['gross', 'tare'], true), 422);
        if ($manual) {
            $this->reasons->assert('override', $reason);
        }
        $ticket = Weighment::query()
            ->where('gate_entry_id', $entry->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();
        if (! $ticket) {
            $ticket = Weighment::query()->create([
                'tenant_id' => $entry->tenant_id,
                'branch_id' => $entry->branch_id,
                'gate_entry_id' => $entry->id,
                'booking_id' => $entry->booking_id,
                'vehicle_no' => $entry->vehicle_no,
                'number' => $this->nextNumber($entry),
                'kind' => $kind,
                'weight' => $weight,
                'is_manual' => $manual,
                'manual_reason' => $manual ? $reason : null,
                'status' => $manual ? 'pending_approval' : 'recorded',
                'tolerance_status' => 'ok',
                'created_by' => $actor->id,
                'weighed_at' => now(),
            ]);
        }
        $data = [
            'kind' => $kind,
            'weight' => $weight,
            'weighed_at' => now(),
            'vehicle_no' => $entry->vehicle_no,
            'booking_id' => $entry->booking_id,
        ];
        if ($kind === 'gross') {
            $data['gross_weight'] = $weight;
        } else {
            $data['tare_weight'] = $weight;
        }
        if ($manual) {
            $data['is_manual'] = true;
            $data['manual_reason'] = $reason;
            $data['status'] = 'pending_approval';
        }
        $ticket->fill($data);
        $ticket->net_weight = $this->net($ticket->gross_weight, $ticket->tare_weight);
        $ticket->save();
        $this->evaluateTolerance($ticket);
        $this->audit->record($manual ? 'weighment.manual_recorded' : 'weighment.recorded', $ticket, [
            'kind' => $kind, 'weight' => $weight,
        ], $entry->tenant_id, ['module' => 'weighbridge', 'branch_id' => $entry->branch_id]);

        return $ticket->fresh();
    }

    public function approveManual(User $actor, Weighment $weighment): void
    {
        abort_unless($this->canApprove($actor), 403);
        abort_unless($weighment->status === 'pending_approval', 422);
        $this->makerChecker->assertCanApprove($actor, $weighment->created_by);
        $this->guard($actor, $weighment->branch);
        $weighment->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->record('weighment.manual_approved', $weighment, [], $weighment->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
        ]);
    }

    public function edit(User $actor, Weighment $weighment, array $data, ?string $reason): void
    {
        $this->guard($actor, $weighment->branch);
        if ($this->canEditDirect($actor)) {
            $weighment->update([
                'gross_weight' => $data['gross_weight'] ?? $weighment->gross_weight,
                'tare_weight' => $data['tare_weight'] ?? $weighment->tare_weight,
                'net_weight' => $this->net($data['gross_weight'] ?? $weighment->gross_weight, $data['tare_weight'] ?? $weighment->tare_weight),
                'weight' => $data['gross_weight'] ?? $data['tare_weight'] ?? $weighment->weight,
            ]);
            $this->evaluateTolerance($weighment->fresh());
            $this->audit->record('weighment.updated', $weighment, ['reason' => $reason], $weighment->tenant_id, [
                'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
            ]);

            return;
        }
        abort_unless($this->canProposeEdit($actor), 403);
        $this->reasons->assert('edit_after_approval', $reason);
        $request = WeighmentEditRequest::query()->create([
            'tenant_id' => $weighment->tenant_id,
            'branch_id' => $weighment->branch_id,
            'weighment_id' => $weighment->id,
            'gross_weight' => $data['gross_weight'] ?? $weighment->gross_weight,
            'tare_weight' => $data['tare_weight'] ?? $weighment->tare_weight,
            'reason' => $reason,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('weighment.edit_proposed', $request, [], $weighment->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
        ]);
    }

    public function decideEdit(User $actor, WeighmentEditRequest $request, bool $approve): void
    {
        abort_unless($this->canApprove($actor) || $this->canEditDirect($actor), 403);
        abort_unless($request->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $request->created_by);
        $this->guard($actor, $request->weighment->branch);
        $request->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $actor->id]);
        if ($approve) {
            $ticket = $request->weighment;
            $ticket->update([
                'gross_weight' => $request->gross_weight,
                'tare_weight' => $request->tare_weight,
                'net_weight' => $this->net($request->gross_weight, $request->tare_weight),
            ]);
            $this->evaluateTolerance($ticket->fresh());
        }
        $this->audit->record($approve ? 'weighment.edit_approved' : 'weighment.edit_rejected', $request, [], $request->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $request->branch_id,
        ]);
    }

    public function remove(User $actor, Weighment $weighment, ?string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->reasons->assert('delete', $reason);
        $weighment->delete();
        $this->audit->record('weighment.deleted', $weighment, ['reason' => $reason], $weighment->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
        ]);
    }

    public function configureTolerance(User $actor, Branch $branch, float $limitKg): void
    {
        abort_unless($this->canConfigure($actor), 403);
        $this->guard($actor, $branch);
        $row = WeighbridgeTolerance::query()->updateOrCreate(
            ['branch_id' => $branch->id],
            ['tenant_id' => $branch->tenant_id, 'limit_kg' => $limitKg, 'updated_by' => $actor->id],
        );
        $this->audit->record('weighment.tolerance_configured', $row, ['limit_kg' => $limitKg], $branch->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $branch->id,
        ]);
    }

    public function acknowledge(User $actor, Weighment $weighment, ?string $comment): void
    {
        abort_unless($this->canAcknowledge($actor), 403);
        abort_unless($weighment->tolerance_status === 'alert', 422);
        $this->guard($actor, $weighment->branch);
        $weighment->update([
            'tolerance_status' => 'acknowledged',
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => now(),
            'acknowledge_comment' => $comment,
        ]);
        $this->audit->record('weighment.tolerance_acknowledged', $weighment, ['comment' => $comment], $weighment->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
        ]);
    }

    public function calibrate(User $actor, Branch $branch, array $data): WeighbridgeCalibration
    {
        abort_unless($this->canCalibrate($actor), 403);
        $this->guard($actor, $branch);
        $row = WeighbridgeCalibration::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'weighbridge_name' => $data['weighbridge_name'] ?? 'Main',
            'calibrated_on' => $data['calibrated_on'],
            'next_due_on' => $data['next_due_on'] ?? null,
            'result' => $data['result'] ?? 'pass',
            'certificate_ref' => $data['certificate_ref'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('weighment.calibration_recorded', $row, [], $branch->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $branch->id,
        ]);

        return $row;
    }

    public function printed(User $actor, Weighment $weighment): void
    {
        abort_unless($this->canPrint($actor), 403);
        $this->guard($actor, $weighment->branch);
        $this->audit->record('weighment.printed', $weighment, [], $weighment->tenant_id, [
            'module' => 'weighbridge', 'branch_id' => $weighment->branch_id,
        ]);
    }

    public function net(mixed $gross, mixed $tare): ?float
    {
        if ($gross === null || $tare === null) {
            return null;
        }

        return round((float) $gross - (float) $tare, 3);
    }

    private function nextNumber(GateEntry $entry): string
    {
        return 'W'.now()->format('ymd').'-'.$entry->id.'-'.Str::upper(Str::random(4));
    }

    private function evaluateTolerance(Weighment $ticket): void
    {
        if ($ticket->net_weight === null || $ticket->booking_id === null) {
            return;
        }
        $limit = WeighbridgeTolerance::query()->where('branch_id', $ticket->branch_id)->value('limit_kg');
        if ($limit === null) {
            return;
        }
        $bookingQty = Booking::query()->whereKey($ticket->booking_id)->value('quantity');
        if ($bookingQty === null) {
            return;
        }
        if (abs((float) $ticket->net_weight - (float) $bookingQty) > (float) $limit) {
            $ticket->update(['tolerance_status' => 'alert']);
            $this->audit->record('weighment.tolerance_alert', $ticket, [
                'net' => $ticket->net_weight, 'booking' => $bookingQty, 'limit' => $limit,
            ], $ticket->tenant_id, ['module' => 'weighbridge', 'branch_id' => $ticket->branch_id]);
        }
    }
}
