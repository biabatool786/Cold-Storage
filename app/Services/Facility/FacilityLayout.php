<?php

namespace App\Services\Facility;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\ChamberSetPointChange;
use App\Models\Csms\Level;
use App\Models\Csms\Rack;
use App\Models\Csms\Slot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;

class FacilityLayout
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
    ) {}

    public function capacityOnly(User $user): bool
    {
        return $user->hasRole(RoleName::Accountant);
    }

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('chambers.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEdit(User $user): bool
    {
        return $user->hasPermission('chambers.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Supervisor]));
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('chambers.delete');
    }

    public function canProposeSetPoint(User $user): bool
    {
        return $user->hasPermission('chambers.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canApproveSetPoint(User $user): bool
    {
        return $user->hasPermission('chambers.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canOperate(User $user): bool
    {
        return $user->hasPermission('chambers.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor]));
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function createChamber(User $actor, Branch $branch, array $data): Chamber
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $branch);
        $chamber = Chamber::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'capacity' => $data['capacity'] ?? null,
            'capacity_unit' => $data['capacity_unit'] ?? 'kg',
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('chamber.created', $chamber, ['code' => $chamber->code], $branch->tenant_id, [
            'module' => 'chambers', 'branch_id' => $branch->id, 'new' => ['name' => $chamber->name, 'code' => $chamber->code],
        ]);

        return $chamber;
    }

    public function updateChamber(User $actor, Chamber $chamber, array $data): void
    {
        abort_unless($this->canEdit($actor), 403);
        $this->guard($actor, $chamber->branch);
        $old = $chamber->only(['name', 'capacity', 'capacity_unit']);
        $chamber->update([
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? null,
            'capacity_unit' => $data['capacity_unit'] ?? $chamber->capacity_unit,
        ]);
        $this->audit->record('chamber.updated', $chamber, [], $chamber->tenant_id, [
            'module' => 'chambers', 'branch_id' => $chamber->branch_id, 'old' => $old, 'new' => $chamber->only(['name', 'capacity', 'capacity_unit']),
        ]);
    }

    public function proposeSetPoint(User $actor, Chamber $chamber, ?float $temperature, ?float $humidity): void
    {
        abort_unless($this->canProposeSetPoint($actor), 403);
        $this->guard($actor, $chamber->branch);
        $change = ChamberSetPointChange::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('chamber.set_point_proposed', $change, [], $chamber->tenant_id, [
            'module' => 'chambers', 'branch_id' => $chamber->branch_id, 'new' => ['temperature' => $temperature, 'humidity' => $humidity],
        ]);
    }

    public function decideSetPoint(User $actor, ChamberSetPointChange $change, bool $approve): void
    {
        abort_unless($this->canApproveSetPoint($actor), 403);
        abort_unless($change->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $change->created_by);
        $chamber = Chamber::query()->findOrFail($change->chamber_id);
        $this->guard($actor, $chamber->branch);
        $change->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $actor->id]);
        if ($approve) {
            $chamber->update(['set_point' => $change->temperature, 'humidity_set_point' => $change->humidity]);
        }
        $this->audit->record($approve ? 'chamber.set_point_approved' : 'chamber.set_point_rejected', $change, [], $chamber->tenant_id, [
            'module' => 'chambers', 'branch_id' => $chamber->branch_id, 'new' => ['status' => $change->status],
        ]);
    }

    public function changeStatus(User $actor, Chamber $chamber, string $status): void
    {
        abort_unless($this->canOperate($actor), 403);
        abort_unless(in_array($status, ['active', 'maintenance', 'defrost'], true), 422);
        if ($actor->hasRole(RoleName::Supervisor) && $status !== 'defrost') {
            abort(403, 'A supervisor can only request defrost.');
        }
        $this->guard($actor, $chamber->branch);
        $old = $chamber->status;
        $chamber->update(['status' => $status]);
        $this->audit->record('chamber.status_changed', $chamber, [], $chamber->tenant_id, [
            'module' => 'chambers', 'branch_id' => $chamber->branch_id, 'old' => ['status' => $old], 'new' => ['status' => $status],
        ]);
    }

    public function createRack(User $actor, Chamber $chamber, array $data): Rack
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $chamber->branch);
        $rack = Rack::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'capacity' => $data['capacity'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('rack.created', $rack, ['code' => $rack->code], $chamber->tenant_id, [
            'module' => 'chambers', 'branch_id' => $chamber->branch_id,
        ]);

        return $rack;
    }

    public function updateRack(User $actor, Rack $rack, array $data): void
    {
        abort_unless($this->canEdit($actor), 403);
        $this->guard($actor, $rack->branch);
        $rack->update(['name' => $data['name'], 'capacity' => $data['capacity'] ?? null]);
        $this->audit->record('rack.updated', $rack, [], $rack->tenant_id, ['module' => 'chambers', 'branch_id' => $rack->branch_id]);
    }

    public function createLevel(User $actor, Rack $rack, array $data): Level
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $rack->branch);
        $level = Level::query()->create([
            'tenant_id' => $rack->tenant_id,
            'branch_id' => $rack->branch_id,
            'rack_id' => $rack->id,
            'name' => $data['name'],
            'code' => $data['code'],
            'created_by' => $actor->id,
        ]);
        $this->audit->record('level.created', $level, ['code' => $level->code], $rack->tenant_id, [
            'module' => 'chambers', 'branch_id' => $rack->branch_id,
        ]);

        return $level;
    }

    public function updateLevel(User $actor, Level $level, array $data): void
    {
        abort_unless($this->canEdit($actor), 403);
        $this->guard($actor, $level->branch);
        $level->update(['name' => $data['name']]);
        $this->audit->record('level.updated', $level, [], $level->tenant_id, ['module' => 'chambers', 'branch_id' => $level->branch_id]);
    }

    public function createSlot(User $actor, Level $level, string $code): Slot
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $level->branch);
        $rack = $level->rack;
        $slot = Slot::query()->create([
            'tenant_id' => $level->tenant_id,
            'branch_id' => $level->branch_id,
            'chamber_id' => $rack->chamber_id,
            'rack_id' => $rack->id,
            'level_id' => $level->id,
            'code' => $code,
            'status' => 'available',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('slot.created', $slot, ['code' => $code], $level->tenant_id, [
            'module' => 'chambers', 'branch_id' => $level->branch_id,
        ]);

        return $slot;
    }

    public function updateSlot(User $actor, Slot $slot, string $code): void
    {
        abort_unless($this->canEdit($actor), 403);
        $this->guard($actor, $slot->branch);
        $slot->update(['code' => $code]);
        $this->audit->record('slot.updated', $slot, [], $slot->tenant_id, ['module' => 'chambers', 'branch_id' => $slot->branch_id]);
    }

    public function block(User $actor, Slot $slot, bool $blocked, string $reason): void
    {
        abort_unless($this->canOperate($actor), 403);
        $this->guard($actor, $slot->branch);
        $this->reasons->assert('override', $reason);
        $slot->update([
            'status' => $blocked ? 'blocked' : 'available',
            'blocked_by' => $blocked ? $actor->id : null,
            'blocked_at' => $blocked ? now() : null,
            'block_reason' => $blocked ? $reason : null,
        ]);
        $this->audit->record($blocked ? 'slot.blocked' : 'slot.unblocked', $slot, [], $slot->tenant_id, [
            'module' => 'chambers', 'branch_id' => $slot->branch_id, 'reason' => $reason, 'new' => ['status' => $slot->status],
        ]);
    }

    public function remove(User $actor, Chamber|Rack|Level|Slot $record, ?string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->guard($actor, $record->branch);
        $this->reasons->assert('delete', $reason);
        $record->delete();
        $this->audit->record(strtolower(class_basename($record)).'.deleted', $record, [], $record->tenant_id, [
            'module' => 'chambers', 'branch_id' => $record->branch_id, 'reason' => $reason,
        ]);
    }
}
