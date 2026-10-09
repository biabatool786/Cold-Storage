<?php

namespace App\Services\Inventory;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\LotShrinkageRecord;
use App\Models\Csms\Slot;
use App\Models\Csms\StockAdjustment;
use App\Models\Csms\StockCount;
use App\Models\Csms\StockCountLine;
use App\Models\Csms\StockHold;
use App\Models\Csms\StockLedgerEntry;
use App\Models\Csms\StockTransfer;
use App\Models\Csms\StorageLot;
use App\Models\Csms\ValueAddedService;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly ApprovalLimitEvaluator $limits,
    ) {}

    public function canViewStock(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function canViewLedger(User $user): bool
    {
        return $user->hasPermission('inventory.view') || $user->hasPermission('inventory.print');
    }

    public function canSuggest(User $user): bool
    {
        return $user->hasPermission('inventory.view')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canCreateTransfer(User $user): bool
    {
        return $user->hasPermission('transfers.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveTransfer(User $user): bool
    {
        return $user->hasPermission('transfers.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canExecuteTransfer(User $user): bool
    {
        return $user->hasPermission('transfers.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canHold(User $user): bool
    {
        return $user->hasPermission('inventory.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canReleaseHold(User $user): bool
    {
        return ($user->isSuperAdmin() && $user->hasPermission('inventory.execute'))
            || ($user->hasRole(RoleName::Manager) && $user->hasPermission('inventory.approve'));
    }

    public function canCreateService(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveService(User $user): bool
    {
        return $user->hasPermission('inventory.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canRecordShrinkage(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canCreateCycleCount(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canCreatePhysicalAudit(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canCount(User $user): bool
    {
        return $user->hasPermission('inventory.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canCreateAdjustment(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveAdjustment(User $user): bool
    {
        return $user->hasPermission('inventory.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canPrintLabels(User $user): bool
    {
        return $user->hasPermission('inventory.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canExport(User $user): bool
    {
        return $user->hasPermission('inventory.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Manager, RoleName::Supervisor, RoleName::Accountant,
            ]));
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function guardLot(User $actor, StorageLot $lot): void
    {
        $branch = Branch::query()->find($lot->branch_id);
        abort_unless($branch !== null, 404);
        $this->guard($actor, $branch);
    }

    public function recordReceipt(StorageLot $lot, ?User $actor = null): StockLedgerEntry
    {
        $commodity = Commodity::query()->find($lot->commodity_id);
        $receivedAt = $lot->received_at ?? now();
        $expires = null;
        if ($commodity?->shelf_life_days) {
            $expires = $receivedAt->copy()->addDays((int) $commodity->shelf_life_days)->toDateString();
        }

        $lot->update([
            'received_at' => $receivedAt,
            'expires_on' => $expires,
            'held_qty' => $lot->held_qty ?? 0,
            'quarantined_qty' => $lot->quarantined_qty ?? 0,
            'dispatched_qty' => $lot->dispatched_qty ?? 0,
        ]);

        return $this->ledger($lot, 'receipt', (float) $lot->quantity, 'GRN put-away', $lot::class, $lot->id, null, $lot->slot_id, $actor?->id);
    }

    public function recordDispatchMovement(StorageLot $lot, float $quantity, ?User $actor, string $referenceType, int $referenceId, ?string $reason = null): StockLedgerEntry
    {
        abort_unless($quantity > 0 && $quantity <= $lot->availableQuantity() + 0.0001, 422);
        $before = (float) $lot->quantity;
        $lot->update([
            'quantity' => max(0, $before - $quantity),
            'dispatched_qty' => (float) $lot->dispatched_qty + $quantity,
        ]);
        if ((float) $lot->fresh()->quantity <= 0 && $lot->slot_id) {
            Slot::query()->whereKey($lot->slot_id)->update(['status' => 'available']);
            $lot->update(['slot_id' => null]);
        }

        return $this->ledger(
            $lot->fresh(),
            'dispatch',
            -$quantity,
            $reason ?? 'Outbound dispatch',
            $referenceType,
            $referenceId,
            null,
            null,
            $actor?->id,
        );
    }

    public function recordReturnMovement(StorageLot $lot, float $quantity, ?User $actor, string $referenceType, int $referenceId, ?string $reason = null): StockLedgerEntry
    {
        $lot->update([
            'quantity' => (float) $lot->quantity + $quantity,
            'dispatched_qty' => max(0, (float) $lot->dispatched_qty - $quantity),
            'status' => 'stored',
        ]);

        return $this->ledger(
            $lot->fresh(),
            'return',
            $quantity,
            $reason ?? 'Dispatch return',
            $referenceType,
            $referenceId,
            null,
            null,
            $actor?->id,
        );
    }

    /**
     * @return Collection<int, StorageLot>
     */
    public function suggestions(User $actor, string $mode, ?int $commodityId = null, ?int $customerId = null, int $limit = 20): Collection
    {
        abort_unless($this->canSuggest($actor), 403);
        abort_unless(in_array($mode, ['fifo', 'fefo'], true), 422);

        $query = StorageLot::query()->visibleTo($actor)
            ->where('status', 'stored')
            ->whereNotNull('slot_id')
            ->with(['customer', 'commodity', 'slot', 'chamber']);

        if ($commodityId) {
            $query->where('commodity_id', $commodityId);
        }
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($mode === 'fifo') {
            $query->orderBy('received_at')->orderBy('id');
        } else {
            $query->orderByRaw('expires_on is null')->orderBy('expires_on')->orderBy('id');
        }

        return $query->limit($limit)->get()->filter(fn (StorageLot $lot) => $lot->availableQuantity() > 0)->values();
    }

    public function requestTransfer(User $actor, StorageLot $lot, Slot $toSlot, float $quantity, ?string $reason = null): StockTransfer
    {
        abort_unless($this->canCreateTransfer($actor), 403);
        $this->guardLot($actor, $lot);
        abort_unless($lot->status === 'stored' && $lot->slot_id, 422);
        abort_unless((int) $toSlot->branch_id === (int) $lot->branch_id, 404);
        abort_unless($toSlot->availableForStorage() || (int) $toSlot->id === (int) $lot->slot_id, 422);
        abort_unless($quantity > 0 && $quantity <= $lot->availableQuantity(), 422);

        $transfer = StockTransfer::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'quantity' => $quantity,
            'from_slot_id' => $lot->slot_id,
            'to_slot_id' => $toSlot->id,
            'status' => 'pending',
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('inventory.transfer_requested', $transfer, [], $lot->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $lot->branch_id,
            'new' => ['lot_id' => $lot->id, 'to_slot_id' => $toSlot->id, 'quantity' => $quantity],
        ]);

        return $transfer;
    }

    public function approveTransfer(User $actor, StockTransfer $transfer): StockTransfer
    {
        abort_unless($this->canApproveTransfer($actor), 403);
        $this->guard($actor, $transfer->branch);
        abort_unless($transfer->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $transfer->created_by);

        $transfer->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->audit->record('inventory.transfer_approved', $transfer, [], $transfer->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $transfer->branch_id,
        ]);

        return $transfer->fresh();
    }

    public function executeTransfer(User $actor, StockTransfer $transfer, Slot $sourceScan, Slot $destScan): StockTransfer
    {
        abort_unless($this->canExecuteTransfer($actor), 403);
        $this->guard($actor, $transfer->branch);
        abort_unless($transfer->status === 'approved', 422);
        abort_unless((int) $sourceScan->id === (int) $transfer->from_slot_id, 422);
        abort_unless((int) $destScan->id === (int) $transfer->to_slot_id, 422);
        abort_unless($destScan->availableForStorage() || (int) $destScan->id === (int) $transfer->from_slot_id, 422);

        return DB::transaction(function () use ($actor, $transfer, $sourceScan, $destScan) {
            $lot = $transfer->lot;
            abort_unless((int) $lot->slot_id === (int) $sourceScan->id, 422);

            $from = $lot->slot;
            $lot->update([
                'slot_id' => $destScan->id,
                'chamber_id' => $destScan->chamber_id,
            ]);

            if ($from && (int) $from->id !== (int) $destScan->id) {
                $from->update(['status' => 'available']);
            }
            $destScan->update(['status' => 'occupied']);

            $this->ledger(
                $lot,
                'transfer',
                (float) $transfer->quantity,
                $transfer->reason,
                $transfer::class,
                $transfer->id,
                $sourceScan->id,
                $destScan->id,
                $actor->id,
            );

            $transfer->update([
                'status' => 'completed',
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);

            $this->audit->record('inventory.transfer_executed', $transfer, [], $transfer->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $transfer->branch_id,
                'new' => ['from_slot_id' => $sourceScan->id, 'to_slot_id' => $destScan->id],
            ]);

            return $transfer->fresh();
        });
    }

    public function placeHold(User $actor, StorageLot $lot, string $kind, float $quantity, string $reason): StockHold
    {
        abort_unless($this->canHold($actor), 403);
        $this->guardLot($actor, $lot);
        abort_unless(in_array($kind, ['hold', 'quarantine'], true), 422);
        $this->reasons->assert('override', $reason);
        abort_unless($lot->status === 'stored', 422);
        abort_unless($quantity > 0 && $quantity <= $lot->availableQuantity(), 422);

        $immediate = $actor->isSuperAdmin() || $actor->hasRole(RoleName::Manager);
        $hold = StockHold::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'kind' => $kind,
            'quantity' => $quantity,
            'status' => $immediate ? 'active' : 'requested',
            'reason' => $reason,
            'created_by' => $actor->id,
            'approved_by' => $immediate ? $actor->id : null,
            'activated_at' => $immediate ? now() : null,
        ]);

        if ($immediate) {
            $this->applyHold($lot, $kind, $quantity, $actor, $hold);
        }

        $this->audit->record($immediate ? 'inventory.hold_placed' : 'inventory.hold_requested', $hold, [], $lot->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $lot->branch_id,
            'reason' => $reason,
        ]);

        return $hold;
    }

    public function activateHold(User $actor, StockHold $hold): StockHold
    {
        abort_unless($this->canReleaseHold($actor) || ($this->canHold($actor) && ($actor->isSuperAdmin() || $actor->hasRole(RoleName::Manager))), 403);
        abort_unless($actor->isSuperAdmin() || $actor->hasRole(RoleName::Manager), 403);
        $this->guard($actor, $hold->branch);
        abort_unless($hold->status === 'requested', 422);
        $this->makerChecker->assertCanApprove($actor, $hold->created_by);

        $hold->update([
            'status' => 'active',
            'approved_by' => $actor->id,
            'activated_at' => now(),
        ]);
        $this->applyHold($hold->lot, $hold->kind, (float) $hold->quantity, $actor, $hold);

        $this->audit->record('inventory.hold_activated', $hold, [], $hold->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $hold->branch_id,
        ]);

        return $hold->fresh();
    }

    public function releaseHold(User $actor, StockHold $hold, string $reason): StockHold
    {
        abort_unless($this->canReleaseHold($actor), 403);
        $this->guard($actor, $hold->branch);
        $this->reasons->assert('reversal', $reason);
        abort_unless($hold->status === 'active', 422);

        return DB::transaction(function () use ($actor, $hold, $reason) {
            $lot = $hold->lot;
            $held = (float) $lot->held_qty;
            $quarantined = (float) $lot->quarantined_qty;
            $qty = (float) $hold->quantity;

            if ($hold->kind === 'quarantine') {
                $quarantined = max(0, $quarantined - $qty);
            } else {
                $held = max(0, $held - $qty);
            }

            $holdStatus = null;
            if ($quarantined > 0) {
                $holdStatus = 'quarantine';
            } elseif ($held > 0) {
                $holdStatus = 'hold';
            }

            $lot->update([
                'held_qty' => $held,
                'quarantined_qty' => $quarantined,
                'hold_status' => $holdStatus,
            ]);

            $this->ledger($lot->fresh(), 'release', $qty, $reason, $hold::class, $hold->id, null, null, $actor->id);

            $hold->update([
                'status' => 'released',
                'released_by' => $actor->id,
                'released_at' => now(),
            ]);

            $this->audit->record('inventory.hold_released', $hold, [], $hold->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $hold->branch_id,
                'reason' => $reason,
            ]);

            return $hold->fresh();
        });
    }

    public function createService(User $actor, StorageLot $lot, string $type, ?float $quantity, ?string $notes): ValueAddedService
    {
        abort_unless($this->canCreateService($actor), 403);
        $this->guardLot($actor, $lot);
        abort_unless(in_array($type, ['sorting', 'grading', 'repacking'], true), 422);

        $service = ValueAddedService::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'service_type' => $type,
            'quantity' => $quantity,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('inventory.service_created', $service, [], $lot->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $lot->branch_id,
        ]);

        return $service;
    }

    public function approveService(User $actor, ValueAddedService $service): ValueAddedService
    {
        abort_unless($this->canApproveService($actor), 403);
        $this->guard($actor, $service->branch);
        abort_unless($service->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $service->created_by);

        $service->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $this->ledger(
            $service->lot,
            'service',
            (float) ($service->quantity ?? 0),
            $service->service_type,
            $service::class,
            $service->id,
            null,
            null,
            $actor->id,
        );

        $this->audit->record('inventory.service_approved', $service, [], $service->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $service->branch_id,
        ]);

        return $service->fresh();
    }

    public function recordShrinkage(User $actor, StorageLot $lot, float $quantity, string $reason, string $recordedOn): LotShrinkageRecord
    {
        abort_unless($this->canRecordShrinkage($actor), 403);
        $this->guardLot($actor, $lot);
        $this->reasons->assert('override', $reason);
        abort_unless($lot->status === 'stored', 422);
        abort_unless($quantity > 0 && $quantity <= $lot->availableQuantity(), 422);

        return DB::transaction(function () use ($actor, $lot, $quantity, $reason, $recordedOn) {
            $before = (float) $lot->quantity;
            $lot->update(['quantity' => $before - $quantity]);

            $row = LotShrinkageRecord::query()->create([
                'tenant_id' => $lot->tenant_id,
                'branch_id' => $lot->branch_id,
                'storage_lot_id' => $lot->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'recorded_on' => $recordedOn,
                'created_by' => $actor->id,
            ]);

            $this->ledger($lot->fresh(), 'shrinkage', -$quantity, $reason, $row::class, $row->id, null, null, $actor->id);

            $this->audit->record('inventory.shrinkage_recorded', $row, [
                'quantity_before' => $before,
                'quantity_after' => $before - $quantity,
            ], $lot->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $lot->branch_id,
                'reason' => $reason,
            ]);

            return $row;
        });
    }

    public function createCount(User $actor, Branch $branch, string $kind, ?Chamber $chamber = null, ?string $notes = null): StockCount
    {
        if ($kind === 'physical') {
            abort_unless($this->canCreatePhysicalAudit($actor), 403);
        } else {
            abort_unless($this->canCreateCycleCount($actor), 403);
            $kind = 'cycle';
        }
        $this->guard($actor, $branch);
        if ($chamber) {
            abort_unless((int) $chamber->branch_id === (int) $branch->id, 404);
        }

        return DB::transaction(function () use ($actor, $branch, $kind, $chamber, $notes) {
            $count = StockCount::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'chamber_id' => $chamber?->id,
                'kind' => $kind,
                'status' => 'open',
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            $lots = StorageLot::query()
                ->where('branch_id', $branch->id)
                ->where('status', 'stored')
                ->when($chamber, fn ($q) => $q->where('chamber_id', $chamber->id))
                ->get();

            foreach ($lots as $lot) {
                StockCountLine::query()->create([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'stock_count_id' => $count->id,
                    'storage_lot_id' => $lot->id,
                    'system_qty' => $lot->quantity,
                ]);
            }

            $this->audit->record('inventory.count_created', $count, [], $branch->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $branch->id,
                'new' => ['kind' => $kind, 'lines' => $lots->count()],
            ]);

            return $count;
        });
    }

    public function recordCountLine(User $actor, StockCountLine $line, float $countedQty): StockCountLine
    {
        abort_unless($this->canCount($actor), 403);
        $this->guard($actor, $line->count->branch);
        abort_unless($line->count->status === 'open', 422);

        $line->update([
            'counted_qty' => $countedQty,
            'variance' => $countedQty - (float) $line->system_qty,
            'counted_by' => $actor->id,
            'counted_at' => now(),
        ]);

        $this->audit->record('inventory.count_line_recorded', $line, [], $line->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $line->branch_id,
            'new' => ['counted_qty' => $countedQty, 'variance' => $line->variance],
        ]);

        return $line->fresh();
    }

    public function completeCount(User $actor, StockCount $count): StockCount
    {
        abort_unless($this->canCount($actor), 403);
        $this->guard($actor, $count->branch);
        abort_unless($count->status === 'open', 422);

        $count->update(['status' => 'completed', 'completed_at' => now()]);
        $this->audit->record('inventory.count_completed', $count, [], $count->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $count->branch_id,
        ]);

        return $count->fresh();
    }

    public function requestAdjustment(User $actor, StorageLot $lot, string $kind, float $quantityAfter, string $reason): StockAdjustment
    {
        abort_unless($this->canCreateAdjustment($actor), 403);
        $this->guardLot($actor, $lot);
        abort_unless(in_array($kind, ['adjustment', 'write_off'], true), 422);
        $this->reasons->assert('override', $reason);
        abort_unless($lot->status === 'stored', 422);
        abort_unless($quantityAfter >= 0, 422);

        if ($kind === 'write_off') {
            abort_unless($quantityAfter < (float) $lot->quantity, 422);
        }

        $before = (float) $lot->quantity;
        $delta = $quantityAfter - $before;

        $adjustment = StockAdjustment::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'kind' => $kind,
            'quantity_before' => $before,
            'quantity_after' => $quantityAfter,
            'delta' => $delta,
            'reason' => $reason,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('inventory.adjustment_requested', $adjustment, [
            'quantity_before' => $before,
            'quantity_after' => $quantityAfter,
        ], $lot->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $lot->branch_id,
            'reason' => $reason,
        ]);

        return $adjustment;
    }

    public function approveAdjustment(User $actor, StockAdjustment $adjustment): StockAdjustment
    {
        abort_unless($this->canApproveAdjustment($actor), 403);
        $this->guard($actor, $adjustment->branch);
        abort_unless($adjustment->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $adjustment->created_by);

        $magnitude = abs((float) $adjustment->delta);
        if ($adjustment->kind === 'write_off' || $adjustment->kind === 'adjustment') {
            $this->limits->assertCanApprove($actor, 'stock_adjustment', $magnitude);
        }

        return DB::transaction(function () use ($actor, $adjustment) {
            $lot = $adjustment->lot;
            $lot->update(['quantity' => $adjustment->quantity_after]);

            $this->ledger(
                $lot->fresh(),
                $adjustment->kind === 'write_off' ? 'write_off' : 'adjustment',
                (float) $adjustment->delta,
                $adjustment->reason,
                $adjustment::class,
                $adjustment->id,
                null,
                null,
                $actor->id,
            );

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $this->audit->record('inventory.adjustment_approved', $adjustment, [
                'quantity_before' => $adjustment->quantity_before,
                'quantity_after' => $adjustment->quantity_after,
            ], $adjustment->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $adjustment->branch_id,
                'reason' => $adjustment->reason,
            ]);

            return $adjustment->fresh();
        });
    }

    private function applyHold(StorageLot $lot, string $kind, float $quantity, User $actor, StockHold $hold): void
    {
        if ($kind === 'quarantine') {
            $lot->update([
                'quarantined_qty' => (float) $lot->quarantined_qty + $quantity,
                'hold_status' => 'quarantine',
            ]);
        } else {
            $lot->update([
                'held_qty' => (float) $lot->held_qty + $quantity,
                'hold_status' => $lot->hold_status === 'quarantine' ? 'quarantine' : 'hold',
            ]);
        }

        $this->ledger($lot->fresh(), $kind, $quantity, $hold->reason, $hold::class, $hold->id, null, null, $actor->id);
    }

    private function ledger(
        StorageLot $lot,
        string $type,
        float $quantity,
        ?string $reason,
        ?string $referenceType,
        ?int $referenceId,
        ?int $fromSlotId,
        ?int $toSlotId,
        ?int $userId,
    ): StockLedgerEntry {
        return StockLedgerEntry::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $lot->quantity,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'from_slot_id' => $fromSlotId,
            'to_slot_id' => $toSlotId,
            'created_by' => $userId,
        ]);
    }
}
