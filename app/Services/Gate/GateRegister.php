<?php

namespace App\Services\Gate;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\GateBlock;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GatePass;
use App\Models\Csms\GateVisitor;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\ReasonRequirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class GateRegister
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly ReasonRequirement $reasons,
    ) {}

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('gate.create') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper));
    }

    public function canEditOpen(User $user, GateEntry $entry): bool
    {
        if ($entry->status !== 'open' || ! $user->hasPermission('gate.edit')) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager)) {
            return true;
        }

        return $user->hasRole(RoleName::Gatekeeper)
            && (int) $entry->created_by === (int) $user->id
            && $entry->created_at?->isToday();
    }

    public function canEditClosed(User $user): bool
    {
        return $user->hasPermission('gate.edit') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('gate.delete');
    }

    public function canExit(User $user): bool
    {
        return $user->hasPermission('gate.execute') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper));
    }

    public function canVerify(User $user): bool
    {
        return $user->hasPermission('gate.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Gatekeeper]));
    }

    public function canOverride(User $user): bool
    {
        return $user->hasPermission('gate.approve') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canVisit(User $user): bool
    {
        return ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Gatekeeper]))
            && ($user->hasPermission('gate.create') || $user->hasPermission('gate.edit'));
    }

    public function canBlock(User $user): bool
    {
        return $user->hasPermission('gate.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canPhoto(User $user): bool
    {
        return $user->hasPermission('gate.execute') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper));
    }

    public function canRecordSeal(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper);
    }

    public function canSeeSeal(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole([RoleName::Supervisor, RoleName::Gatekeeper]);
    }

    public function canPrintToken(User $user): bool
    {
        return $user->hasPermission('gate.print') && ($user->isSuperAdmin() || $user->hasRole(RoleName::Gatekeeper));
    }

    public function canPrintHistory(User $user): bool
    {
        return $user->hasPermission('gate.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function history(User $user): Builder
    {
        abort_unless($user->hasPermission('gate.view'), 403);
        $query = GateEntry::query()->visibleTo($user);
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin()) {
            return $query->where('created_at', '>=', now()->subDays(7));
        }
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin()) {
            return $query->where('created_by', $user->id)->where('created_at', '<', now()->startOfDay());
        }

        return $query;
    }

    public function today(User $user): Builder
    {
        abort_unless($user->hasPermission('gate.view'), 403);

        return GateEntry::query()->visibleTo($user)->whereDate('created_at', today());
    }

    public function enter(User $actor, Branch $branch, array $data): GateEntry
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $branch);
        $entry = GateEntry::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $data['customer_id'] ?? null,
            'booking_id' => $data['booking_id'] ?? null,
            'vehicle_no' => $data['vehicle_no'],
            'driver_name' => $data['driver_name'] ?? null,
            'purpose' => $data['purpose'],
            'status' => 'open',
            'entered_at' => now(),
            'token' => Str::upper(Str::random(8)),
            'seal_number' => $this->canRecordSeal($actor) ? ($data['seal_number'] ?? null) : null,
            'vehicle_condition' => $this->canRecordSeal($actor) ? ($data['vehicle_condition'] ?? null) : null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('gate.entered', $entry, ['token' => $entry->token, 'blocked' => $this->isBlocked($entry)], $branch->tenant_id, [
            'module' => 'gate', 'branch_id' => $branch->id,
        ]);

        return $entry;
    }

    public function update(User $actor, GateEntry $entry, array $data, ?string $reason): void
    {
        $this->guard($actor, $entry->branch);
        if ($entry->status === 'closed') {
            abort_unless($this->canEditClosed($actor), 403);
            if ($actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
                $this->reasons->assert('edit_after_approval', $reason);
            }
        } else {
            abort_unless($this->canEditOpen($actor, $entry), 403);
        }
        $changes = [
            'vehicle_no' => $data['vehicle_no'],
            'driver_name' => $data['driver_name'] ?? null,
            'purpose' => $data['purpose'],
        ];
        if ($this->canRecordSeal($actor)) {
            $changes['seal_number'] = $data['seal_number'] ?? $entry->seal_number;
            $changes['vehicle_condition'] = $data['vehicle_condition'] ?? $entry->vehicle_condition;
        }
        $entry->update($changes);
        $this->audit->record('gate.updated', $entry, ['reason' => $reason], $entry->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry->branch_id,
        ]);
    }

    public function remove(User $actor, GateEntry $entry, ?string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->reasons->assert('delete', $reason);
        $entry->delete();
        $this->audit->record('gate.deleted', $entry, ['reason' => $reason], $entry->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry->branch_id,
        ]);
    }

    public function photo(User $actor, GateEntry $entry, UploadedFile $file): void
    {
        abort_unless($this->canPhoto($actor), 403);
        $this->guard($actor, $entry->branch);
        $path = $file->store('gate-photos', 'public');
        $entry->update(['photo_path' => $path]);
        $this->audit->record('gate.photo_stored', $entry, [], $entry->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry->branch_id,
        ]);
    }

    public function verify(User $actor, GateEntry $entry, string $code): void
    {
        abort_unless($this->canVerify($actor), 403);
        $this->guard($actor, $entry->branch);
        $pass = GatePass::query()->where('branch_id', $entry->branch_id)
            ->where(fn ($query) => $query->where('code', $code)->orWhere('id', $code))
            ->first();
        abort_unless($pass, 422);
        $entry->update(['pass_verified_at' => now(), 'gate_pass_id' => $pass->id]);
        $this->audit->record('gate.pass_verified', $entry, ['code' => $code], $entry->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry->branch_id,
        ]);
    }

    public function override(User $actor, GateEntry $entry, ?string $reason): void
    {
        abort_unless($this->canOverride($actor), 403);
        $this->guard($actor, $entry->branch);
        $this->reasons->assert('override', $reason);
        $entry->update(['override_approved_by' => $actor->id, 'override_reason' => $reason]);
        $this->audit->record('gate.override_approved', $entry, ['reason' => $reason], $entry->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry->branch_id,
        ]);
    }

    public function visitor(User $actor, Branch $branch, array $data): GateVisitor
    {
        abort_unless($this->canVisit($actor), 403);
        $this->guard($actor, $branch);
        $visitor = GateVisitor::query()->create([
            ...$data,
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('gate.visitor_registered', $visitor, ['name' => $visitor->name], $branch->tenant_id, [
            'module' => 'gate', 'branch_id' => $branch->id,
        ]);

        return $visitor;
    }

    public function block(User $actor, Branch $branch, array $data): GateBlock
    {
        abort_unless($this->canBlock($actor), 403);
        $this->guard($actor, $branch);
        abort_if(blank($data['vehicle_no'] ?? null) && blank($data['driver_name'] ?? null), 422);
        $block = GateBlock::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'vehicle_no' => $data['vehicle_no'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
            'reason' => $data['reason'],
            'created_by' => $actor->id,
        ]);
        $this->audit->record('gate.blocked', $block, [], $branch->tenant_id, [
            'module' => 'gate', 'branch_id' => $branch->id,
        ]);

        return $block;
    }

    public function isBlocked(GateEntry $entry): bool
    {
        return GateBlock::query()->where('branch_id', $entry->branch_id)->where(function ($query) use ($entry) {
            $query->when($entry->vehicle_no, fn ($query) => $query->orWhere('vehicle_no', $entry->vehicle_no))
                ->when($entry->driver_name, fn ($query) => $query->orWhere('driver_name', $entry->driver_name));
        })->exists();
    }

    public function expected(User $user)
    {
        abort_unless($user->hasPermission('gate.view'), 403);

        return Booking::query()->visibleTo($user)->whereNotNull('vehicle_no')->where(function ($query) {
            $query->whereDate('starts_on', today())->orWhere(function ($query) {
                $query->whereNull('starts_on')->whereDate('created_at', today());
            });
        })->latest()->limit(50)->get();
    }

    public function printed(User $actor, string $action, ?GateEntry $entry = null): void
    {
        $allowed = $action === 'gate.token_printed' ? $this->canPrintToken($actor) : $this->canPrintHistory($actor);
        abort_unless($allowed, 403);
        $this->audit->record($action, $entry, [], $entry?->tenant_id ?? $actor->tenant_id, [
            'module' => 'gate', 'branch_id' => $entry?->branch_id,
        ]);
    }
}
