<?php

namespace App\Services\Dispatch;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\BranchTransferOut;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\DeliveryPickLine;
use App\Models\Csms\DispatchReturn;
use App\Models\Csms\GatePass;
use App\Models\Csms\Slot;
use App\Models\Csms\StorageLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Inventory\InventoryDesk;
use App\Services\Notifications\OperationalAlertHooks;
use App\Services\Receivables\ReceivablesDesk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DispatchDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly ApprovalLimitEvaluator $limits,
        private readonly InventoryDesk $inventory,
        private readonly ReceivablesDesk $receivables,
        private readonly OperationalAlertHooks $operationalAlerts,
    ) {}

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('dispatch.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canView(User $user): bool
    {
        return $user->hasPermission('dispatch.view');
    }

    public function canEditDraft(User $user, DeliveryOrder $order): bool
    {
        if (! $user->hasPermission('dispatch.edit') || ! $order->isEditableDraft()) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager)) {
            return true;
        }

        return $user->hasRole(RoleName::Supervisor) && (int) $order->created_by === (int) $user->id;
    }

    public function canApprove(User $user): bool
    {
        return $user->hasPermission('dispatch.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canRecommendDues(User $user): bool
    {
        return $user->hasPermission('dispatch.approve') && $user->hasRole(RoleName::Accountant);
    }

    public function canCancel(User $user): bool
    {
        return $user->hasPermission('dispatch.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canPick(User $user): bool
    {
        return $user->hasPermission('dispatch.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canOverrideFifo(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasRole(RoleName::Manager) && $user->hasPermission('dispatch.approve'))
            || ($user->hasRole(RoleName::Supervisor) && $user->hasPermission('dispatch.execute'));
    }

    public function canLoad(User $user): bool
    {
        return $user->hasPermission('dispatch.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canIssuePass(User $user): bool
    {
        return $user->hasPermission('dispatch.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('dispatch.print');
    }

    public function canVerifyExit(User $user): bool
    {
        return ($user->hasPermission('gate.execute') || $user->hasPermission('dispatch.execute'))
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Gatekeeper]));
    }

    public function canEditApproved(User $user): bool
    {
        return $user->hasPermission('dispatch.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canHandleReturns(User $user): bool
    {
        return $user->hasPermission('dispatch.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveReturn(User $user): bool
    {
        return $user->hasPermission('dispatch.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canTransferOut(User $user): bool
    {
        return $user->hasPermission('dispatch.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveTransferOut(User $user): bool
    {
        return $user->hasPermission('dispatch.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('dispatch.delete');
    }

    public function seesDuesAmount(User $user): bool
    {
        return ! $user->hasRole(RoleName::Supervisor) && ! $user->hasRole(RoleName::Gatekeeper);
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function guardOrder(User $actor, DeliveryOrder $order): void
    {
        $branch = Branch::query()->find($order->branch_id);
        abort_unless($branch !== null, 404);
        $this->guard($actor, $branch);
    }

    /**
     * @return array{dues: float, stock_ok: bool, credit_ok: bool, customer_ok: bool, available: float, credit_limit: float}
     */
    public function runChecks(StorageLot $lot, float $quantity): array
    {
        $dues = $this->receivables->customerOutstanding((int) $lot->customer_id);
        $customer = Customer::query()->find($lot->customer_id);
        $available = $lot->availableQuantity();
        $creditLimit = (float) ($customer?->credit_limit ?? 0);
        $stockOk = $quantity > 0 && $quantity <= $available && $lot->status === 'stored'
            && ! in_array($lot->hold_status, ['hold', 'quarantine'], true);
        $creditOk = $creditLimit <= 0 || $dues <= $creditLimit;
        $customerOk = $customer === null || ! $this->receivables->customerOnDuesHold($customer);

        return [
            'dues' => $dues,
            'stock_ok' => $stockOk,
            'credit_ok' => $creditOk,
            'customer_ok' => $customerOk,
            'available' => $available,
            'credit_limit' => $creditLimit,
        ];
    }

    public function open(User $actor, StorageLot $lot, float $quantity, string $pickMode = 'fifo', bool $submit = true): DeliveryOrder
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $lot->branch);
        abort_unless($lot->status === 'stored', 422);
        abort_unless($quantity > 0, 422);
        abort_unless(in_array($pickMode, ['fifo', 'fefo'], true), 422);

        $checks = $this->runChecks($lot, $quantity);
        if (! $checks['stock_ok']) {
            $this->operationalAlerts->lowStockDispatch($lot, $quantity, 'delivery open');
            abort(422);
        }

        $order = DeliveryOrder::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'customer_id' => $lot->customer_id,
            'commodity_id' => $lot->commodity_id,
            'number' => $this->nextNumber($lot->tenant_id, $lot->branch_id),
            'quantity' => $quantity,
            'remaining_qty' => $quantity,
            'dues_amount' => $checks['dues'],
            'stock_ok' => $checks['stock_ok'],
            'credit_ok' => $checks['credit_ok'],
            'has_dues_flag' => $checks['dues'] > 0,
            'pick_mode' => $pickMode,
            'status' => $submit ? 'pending' : 'draft',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('delivery.opened', $order, [], $lot->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $lot->branch_id,
            'new' => ['dues_amount' => $order->dues_amount, 'stock_ok' => $checks['stock_ok'], 'credit_ok' => $checks['credit_ok']],
        ]);

        if ($submit) {
            $this->operationalAlerts->pendingApproval(
                $order,
                (int) $order->tenant_id,
                (int) $order->branch_id,
                'Delivery order',
                'Delivery order '.$order->number.' awaits approval (qty '.round((float) $order->quantity, 3).').'
            );
        }

        return $order;
    }

    public function updateDraft(User $actor, DeliveryOrder $order, array $data): DeliveryOrder
    {
        abort_unless($this->canEditDraft($actor, $order), 403);
        $this->guardOrder($actor, $order);
        $qty = isset($data['quantity']) ? (float) $data['quantity'] : (float) $order->quantity;
        $checks = $this->runChecks($order->lot, $qty);
        if (! $checks['stock_ok']) {
            $this->operationalAlerts->lowStockDispatch($order->lot, $qty, 'delivery draft '.$order->number);
            abort(422);
        }

        $wasPending = $order->status === 'pending';
        $order->update([
            'quantity' => $qty,
            'remaining_qty' => $qty,
            'pick_mode' => $data['pick_mode'] ?? $order->pick_mode,
            'dues_amount' => $checks['dues'],
            'stock_ok' => $checks['stock_ok'],
            'credit_ok' => $checks['credit_ok'],
            'has_dues_flag' => $checks['dues'] > 0,
            'status' => ! empty($data['submit']) ? 'pending' : $order->status,
        ]);
        $fresh = $order->fresh();
        if (! $wasPending && $fresh->status === 'pending') {
            $this->operationalAlerts->pendingApproval(
                $fresh,
                (int) $fresh->tenant_id,
                (int) $fresh->branch_id,
                'Delivery order',
                'Delivery order '.$fresh->number.' awaits approval (qty '.round((float) $fresh->quantity, 3).').'
            );
        }

        return $fresh;
    }

    public function editApproved(User $actor, DeliveryOrder $order, array $data, string $reason): DeliveryOrder
    {
        abort_unless($this->canEditApproved($actor), 403);
        abort_unless(in_array($order->status, ['approved', 'picking', 'picked'], true), 422);
        $this->guardOrder($actor, $order);
        $this->reasons->assert('edit_after_approval', $reason);
        $before = $order->only(['quantity', 'loader_notes', 'outgoing_temperature']);
        $order->update([
            'quantity' => $data['quantity'] ?? $order->quantity,
            'loader_notes' => $data['loader_notes'] ?? $order->loader_notes,
            'outgoing_temperature' => $data['outgoing_temperature'] ?? $order->outgoing_temperature,
            'last_edit_reason' => $reason,
        ]);
        $this->audit->record('delivery.edited_after_approval', $order, $before, $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'reason' => $reason,
        ]);

        return $order->fresh();
    }

    public function recommendDues(User $actor, DeliveryOrder $order, string $note): DeliveryOrder
    {
        abort_unless($this->canRecommendDues($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless($order->status === 'pending' && $order->has_dues_flag, 422);
        $order->update(['dues_recommended_by' => $actor->id, 'dues_recommend_note' => $note]);
        $this->audit->record('delivery.dues_recommended', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'reason' => $note,
        ]);

        return $order->fresh();
    }

    public function approve(User $actor, DeliveryOrder $order): DeliveryOrder
    {
        abort_unless($this->canApprove($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless($order->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $order->created_by);

        $checks = $this->runChecks($order->lot, (float) $order->quantity);
        if (! $checks['stock_ok']) {
            $this->operationalAlerts->lowStockDispatch($order->lot, (float) $order->quantity, 'delivery approve '.$order->number);
            abort(422);
        }
        abort_unless($checks['customer_ok'], 422);
        $order->update([
            'dues_amount' => $checks['dues'],
            'stock_ok' => $checks['stock_ok'],
            'credit_ok' => $checks['credit_ok'],
            'has_dues_flag' => $checks['dues'] > 0,
        ]);

        if ($checks['dues'] > 0) {
            $this->limits->assertCanApprove($actor, 'delivery_order_overdue', $checks['dues']);
        }

        $order->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->record('delivery.approved', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
        ]);

        return $order->fresh();
    }

    public function reject(User $actor, DeliveryOrder $order, string $reason): DeliveryOrder
    {
        abort_unless($this->canCancel($actor), 403);
        $this->guardOrder($actor, $order);
        $this->reasons->assert('reversal', $reason);
        abort_unless(in_array($order->status, ['draft', 'pending', 'approved'], true), 422);
        $order->update([
            'status' => 'rejected',
            'cancel_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ]);
        $this->audit->record('delivery.rejected', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'reason' => $reason,
        ]);

        return $order->fresh();
    }

    public function cancel(User $actor, DeliveryOrder $order, string $reason): DeliveryOrder
    {
        abort_unless($this->canCancel($actor), 403);
        $this->guardOrder($actor, $order);
        $this->reasons->assert('reversal', $reason);
        abort_unless(! in_array($order->status, ['dispatched', 'cancelled'], true), 422);
        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ]);
        $this->audit->record('delivery.cancelled', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'reason' => $reason,
        ]);

        return $order->fresh();
    }

    public function generatePickList(User $actor, DeliveryOrder $order, bool $overrideFifo = false, ?string $overrideReason = null): DeliveryOrder
    {
        abort_unless($this->canPick($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless(in_array($order->status, ['approved', 'picking'], true), 422);

        if ($overrideFifo) {
            abort_unless($this->canOverrideFifo($actor), 403);
            if ($actor->hasRole(RoleName::Supervisor) && ! $actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
                $this->reasons->assert('override', $overrideReason);
            }
            $order->update([
                'fifo_override' => true,
                'fifo_override_reason' => $overrideReason,
                'fifo_override_by' => $actor->id,
            ]);
        }

        $order->pickLines()->each(fn (DeliveryPickLine $line) => $line->delete());

        $remaining = (float) $order->quantity;
        $primary = $order->lot;
        $lines = [];
        if ($primary && $primary->availableQuantity() > 0) {
            $take = min($remaining, $primary->availableQuantity());
            $lines[] = [$primary, $take];
            $remaining -= $take;
        }

        if ($remaining > 0 && ! $order->fifo_override) {
            $suggestions = $this->inventory->suggestions($actor, $order->pick_mode ?: 'fifo', $order->commodity_id, $order->customer_id, 50);
            foreach ($suggestions as $lot) {
                if ($remaining <= 0) {
                    break;
                }
                if ((int) $lot->id === (int) $primary?->id) {
                    continue;
                }
                $take = min($remaining, $lot->availableQuantity());
                if ($take <= 0) {
                    continue;
                }
                $lines[] = [$lot, $take];
                $remaining -= $take;
            }
        }

        if ($remaining > 0.0001) {
            if ($primary) {
                $this->operationalAlerts->lowStockDispatch($primary, (float) $order->quantity, 'pick list '.$order->number);
            }
            throw ValidationException::withMessages(['quantity' => 'Insufficient eligible stock for the pick list.']);
        }

        $orderNo = 0;
        foreach ($lines as [$lot, $qty]) {
            DeliveryPickLine::query()->create([
                'tenant_id' => $order->tenant_id,
                'branch_id' => $order->branch_id,
                'delivery_order_id' => $order->id,
                'storage_lot_id' => $lot->id,
                'slot_id' => $lot->slot_id,
                'suggested_qty' => $qty,
                'sort_order' => $orderNo++,
                'is_override' => (bool) $order->fifo_override,
                'status' => 'pending',
            ]);
        }

        $order->update(['status' => 'picking']);
        $this->audit->record('delivery.pick_list_generated', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'new' => ['lines' => count($lines), 'override' => (bool) $order->fifo_override],
        ]);

        return $order->fresh(['pickLines.lot', 'pickLines.slot']);
    }

    public function pickLine(User $actor, DeliveryPickLine $line, float $pickedQty, ?Slot $scannedSlot = null): DeliveryPickLine
    {
        abort_unless($this->canPick($actor), 403);
        $this->guardOrder($actor, $line->order);
        abort_unless($line->order->status === 'picking', 422);
        abort_unless($pickedQty > 0 && $pickedQty <= (float) $line->suggested_qty + 0.0001, 422);
        if ($scannedSlot) {
            abort_unless((int) $scannedSlot->id === (int) $line->slot_id, 422);
        }
        abort_unless($line->lot->availableQuantity() + 0.0001 >= $pickedQty, 422);

        $line->update([
            'picked_qty' => $pickedQty,
            'status' => 'picked',
            'picked_by' => $actor->id,
            'picked_at' => now(),
        ]);

        $order = $line->order;
        $totalPicked = (float) $order->pickLines()->sum('picked_qty');
        $order->update([
            'picked_qty' => $totalPicked,
            'remaining_qty' => max(0, (float) $order->quantity - $totalPicked),
            'status' => $totalPicked + 0.0001 >= (float) $order->quantity ? 'picked' : 'picking',
        ]);

        $this->audit->record('delivery.line_picked', $line, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'new' => ['picked_qty' => $pickedQty],
        ]);

        return $line->fresh();
    }

    public function recordTemperature(User $actor, DeliveryOrder $order, float $temperature): DeliveryOrder
    {
        abort_unless($this->canPick($actor) || $this->canCreate($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless(in_array($order->status, ['approved', 'picking', 'picked', 'loading'], true), 422);
        $order->update(['outgoing_temperature' => $temperature]);
        $this->audit->record('delivery.temperature_recorded', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'new' => ['outgoing_temperature' => $temperature],
        ]);

        return $order->fresh();
    }

    public function recordLoaders(User $actor, DeliveryOrder $order, int $count, ?string $notes = null): DeliveryOrder
    {
        abort_unless($this->canPick($actor) || $this->canCreate($actor), 403);
        $this->guardOrder($actor, $order);
        $order->update(['loader_count' => $count, 'loader_notes' => $notes]);

        return $order->fresh();
    }

    public function verifyLoading(User $actor, DeliveryOrder $order, float $finalCount): DeliveryOrder
    {
        abort_unless($this->canLoad($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless(in_array($order->status, ['picked', 'loading'], true), 422);
        abort_unless(abs($finalCount - (float) $order->picked_qty) < 0.001, 422);

        $order->update([
            'status' => 'loading',
            'loading_verified' => true,
            'loading_verified_by' => $actor->id,
            'loading_verified_at' => now(),
        ]);
        $this->audit->record('delivery.loading_verified', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'new' => ['final_count' => $finalCount],
        ]);

        return $order->fresh();
    }

    public function issuePass(User $actor, DeliveryOrder $order): GatePass
    {
        abort_unless($this->canIssuePass($actor), 403);
        $this->guardOrder($actor, $order);

        if ($order->status === 'approved') {
            $this->generatePickList($actor, $order);
            $order->refresh();
            foreach ($order->pickLines as $line) {
                $this->pickLine($actor, $line, (float) $line->suggested_qty, $line->slot);
            }
            $order->refresh();
            $this->verifyLoading($actor, $order, (float) $order->picked_qty);
            $order->refresh();
        }

        abort_unless($order->loading_verified && in_array($order->status, ['loading', 'picked'], true), 422);
        abort_unless(! GatePass::query()->where('delivery_order_id', $order->id)->where('status', 'issued')->exists(), 422);

        $pass = GatePass::query()->create([
            'tenant_id' => $order->tenant_id,
            'branch_id' => $order->branch_id,
            'delivery_order_id' => $order->id,
            'status' => 'issued',
            'code' => strtoupper(Str::random(8)),
            'created_by' => $actor->id,
        ]);
        $order->update(['status' => 'loaded']);
        $this->audit->record('gate_pass.issued', $pass, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
        ]);

        return $pass;
    }

    public function verifyExit(User $actor, GatePass $pass): GatePass
    {
        abort_unless($this->canVerifyExit($actor), 403);
        $this->guard($actor, $pass->branch);
        abort_unless($pass->status === 'issued', 422);

        return DB::transaction(function () use ($actor, $pass) {
            $order = $pass->deliveryOrder;
            foreach ($order->pickLines()->where('status', 'picked')->get() as $line) {
                $qty = (float) $line->picked_qty;
                if ($qty <= 0) {
                    continue;
                }
                $this->inventory->recordDispatchMovement(
                    $line->lot,
                    $qty,
                    $actor,
                    $order::class,
                    $order->id,
                    'DO '.$order->number,
                );
            }

            $pass->update([
                'status' => 'released',
                'released_at' => now(),
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $order->update([
                'status' => 'dispatched',
                'dispatched_qty' => $order->picked_qty,
                'remaining_qty' => max(0, (float) $order->quantity - (float) $order->picked_qty),
            ]);

            $this->audit->record('gate.released', $pass, [], $pass->tenant_id, [
                'module' => 'dispatch',
                'branch_id' => $pass->branch_id,
            ]);

            return $pass->fresh();
        });
    }

    public function createReturn(User $actor, DeliveryOrder $order, float $quantity, string $reason, ?string $condition = null): DispatchReturn
    {
        abort_unless($this->canHandleReturns($actor), 403);
        $this->guardOrder($actor, $order);
        abort_unless(in_array($order->status, ['dispatched', 'loaded'], true), 422);
        abort_unless($quantity > 0 && $quantity <= (float) $order->dispatched_qty + (float) $order->picked_qty, 422);

        $immediate = $actor->isSuperAdmin() || $actor->hasRole(RoleName::Manager);
        $row = DispatchReturn::query()->create([
            'tenant_id' => $order->tenant_id,
            'branch_id' => $order->branch_id,
            'delivery_order_id' => $order->id,
            'storage_lot_id' => $order->storage_lot_id,
            'quantity' => $quantity,
            'reason' => $reason,
            'condition' => $condition,
            'status' => $immediate ? 'approved' : 'pending',
            'created_by' => $actor->id,
            'approved_by' => $immediate ? $actor->id : null,
            'approved_at' => $immediate ? now() : null,
        ]);

        if ($immediate) {
            $this->applyReturn($actor, $row);
        }

        $this->audit->record($immediate ? 'delivery.return_approved' : 'delivery.return_requested', $row, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'reason' => $reason,
        ]);

        return $row;
    }

    public function approveReturn(User $actor, DispatchReturn $return): DispatchReturn
    {
        abort_unless($this->canApproveReturn($actor), 403);
        $this->guard($actor, $return->branch);
        abort_unless($return->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $return->created_by);
        $return->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->applyReturn($actor, $return);
        $this->audit->record('delivery.return_approved', $return, [], $return->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $return->branch_id,
        ]);

        return $return->fresh();
    }

    public function requestBranchTransferOut(User $actor, StorageLot $lot, Branch $toBranch, float $quantity, ?string $reason = null): BranchTransferOut
    {
        abort_unless($this->canTransferOut($actor), 403);
        $this->guard($actor, $lot->branch);
        abort_unless((int) $toBranch->tenant_id === (int) $lot->tenant_id, 404);
        abort_unless((int) $toBranch->id !== (int) $lot->branch_id, 422);
        abort_unless($quantity > 0 && $quantity <= $lot->availableQuantity(), 422);

        $row = BranchTransferOut::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'to_branch_id' => $toBranch->id,
            'storage_lot_id' => $lot->id,
            'quantity' => $quantity,
            'status' => 'pending',
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('delivery.transfer_out_requested', $row, [], $lot->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $lot->branch_id,
        ]);

        return $row;
    }

    public function approveBranchTransferOut(User $actor, BranchTransferOut $transfer): BranchTransferOut
    {
        abort_unless($this->canApproveTransferOut($actor), 403);
        $this->guard($actor, $transfer->branch);
        abort_unless($transfer->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $transfer->created_by);

        return DB::transaction(function () use ($actor, $transfer) {
            $lot = $transfer->lot;
            $this->inventory->recordDispatchMovement(
                $lot,
                (float) $transfer->quantity,
                $actor,
                $transfer::class,
                $transfer->id,
                'Branch transfer-out to '.$transfer->to_branch_id,
            );
            $transfer->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->audit->record('delivery.transfer_out_approved', $transfer, [], $transfer->tenant_id, [
                'module' => 'dispatch',
                'branch_id' => $transfer->branch_id,
            ]);

            return $transfer->fresh();
        });
    }

    public function delete(User $actor, DeliveryOrder $order, string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->guardOrder($actor, $order);
        $this->reasons->assert('delete', $reason);
        $this->audit->record('delivery.deleted', $order, ['reason' => $reason], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
        ]);
        $order->pickLines()->each(fn (DeliveryPickLine $line) => $line->delete());
        $order->gatePasses()->each(fn (GatePass $pass) => $pass->delete());
        $order->delete();
    }

    private function applyReturn(User $actor, DispatchReturn $return): void
    {
        if ($return->lot) {
            $this->inventory->recordReturnMovement(
                $return->lot,
                (float) $return->quantity,
                $actor,
                $return::class,
                $return->id,
                $return->reason,
            );
        }
    }

    private function nextNumber(int $tenantId, int $branchId): string
    {
        $seq = DeliveryOrder::query()->where('tenant_id', $tenantId)->withTrashed()->count() + 1;

        return sprintf('DO-B%d-%s-%04d', $branchId, now()->format('Ymd'), $seq);
    }
}
