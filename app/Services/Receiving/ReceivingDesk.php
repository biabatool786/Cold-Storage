<?php

namespace App\Services\Receiving;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\GrnPhoto;
use App\Models\Csms\PutAwayTask;
use App\Models\Csms\Slot;
use App\Models\Csms\StorageLot;
use App\Models\Csms\Weighment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Customers\CustomerMaster;
use App\Services\Inventory\InventoryDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivingDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly CustomerMaster $customers,
        private readonly InventoryDesk $inventory,
    ) {}

    public function canView(User $user): bool
    {
        return $user->hasPermission('grn.view') || $user->hasPermission('receiving.view');
    }

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('grn.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canEditDraft(User $user, GoodsReceipt $receipt): bool
    {
        if (! $user->hasPermission('grn.edit') || ! $receipt->isEditableDraft()) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager)) {
            return true;
        }
        if ($user->hasRole(RoleName::Supervisor)) {
            return (int) $receipt->created_by === (int) $user->id;
        }

        return false;
    }

    public function canProposeOutcome(User $user): bool
    {
        return $user->hasPermission('grn.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Supervisor));
    }

    public function canConfirmOutcome(User $user): bool
    {
        return $user->hasPermission('grn.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canApprove(User $user): bool
    {
        return $user->hasPermission('grn.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canEditApproved(User $user): bool
    {
        return $user->hasPermission('grn.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canCancel(User $user): bool
    {
        return $user->hasPermission('grn.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canDelete(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('grn.delete');
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('grn.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Accountant,
            ]));
    }

    public function canSend(User $user): bool
    {
        return $user->hasPermission('grn.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function canPutAway(User $user): bool
    {
        return $user->hasPermission('inventory.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function canCreatePutAway(User $user): bool
    {
        return $user->hasPermission('inventory.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor]));
    }

    public function isGatekeeperLimited(User $user): bool
    {
        return $user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin();
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function guardReceipt(User $actor, GoodsReceipt $receipt): void
    {
        $branch = Branch::query()->find($receipt->branch_id);
        abort_unless($branch !== null, 404);
        $this->guard($actor, $branch);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(User $actor, GateEntry $entry, array $data): GoodsReceipt
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $entry->branch);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        abort_unless((int) $customer->branch_id === (int) $entry->branch_id, 404);
        $commodity = Commodity::query()->findOrFail($data['commodity_id']);
        abort_unless((int) $commodity->tenant_id === (int) $entry->tenant_id, 404);

        $bookingId = $data['booking_id'] ?? $entry->booking_id;
        if ($bookingId) {
            $booking = Booking::query()->findOrFail($bookingId);
            abort_unless((int) $booking->branch_id === (int) $entry->branch_id, 404);
        }

        $weighment = $this->resolveWeighment($entry, $data['weighment_id'] ?? null);

        $receipt = GoodsReceipt::query()->create([
            'tenant_id' => $entry->tenant_id,
            'branch_id' => $entry->branch_id,
            'gate_entry_id' => $entry->id,
            'booking_id' => $bookingId,
            'customer_id' => $customer->id,
            'commodity_id' => $commodity->id,
            'weighment_id' => $weighment?->id,
            'number' => $this->nextGrnNumber($entry->tenant_id, $entry->branch_id),
            'quantity' => $data['quantity'],
            'grade' => $data['grade'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'condition_notes' => $data['condition_notes'] ?? null,
            'loader_count' => $data['loader_count'] ?? null,
            'unloading_notes' => $data['unloading_notes'] ?? null,
            'shortage_qty' => $data['shortage_qty'] ?? null,
            'damage_qty' => $data['damage_qty'] ?? null,
            'accepted_qty' => $data['accepted_qty'] ?? null,
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'status' => $data['submit'] ?? false ? 'pending' : 'draft',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('grn.created', $receipt, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => ['status' => $receipt->status, 'number' => $receipt->number],
        ]);

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(User $actor, GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        abort_unless($this->canEditDraft($actor, $receipt), 403);
        $this->guardReceipt($actor, $receipt);

        $before = $receipt->only([
            'quantity', 'grade', 'temperature', 'condition_notes', 'loader_count', 'unloading_notes',
            'shortage_qty', 'damage_qty', 'accepted_qty', 'inspection_notes', 'booking_id', 'weighment_id', 'status',
        ]);

        if (isset($data['booking_id'])) {
            $booking = Booking::query()->findOrFail($data['booking_id']);
            abort_unless((int) $booking->branch_id === (int) $receipt->branch_id, 404);
        }
        if (! empty($data['weighment_id'])) {
            $weighment = Weighment::query()->findOrFail($data['weighment_id']);
            abort_unless((int) $weighment->branch_id === (int) $receipt->branch_id, 404);
            abort_unless((int) $weighment->gate_entry_id === (int) $receipt->gate_entry_id, 404);
            $data['weighment_id'] = $weighment->id;
        }

        $updates = array_intersect_key($data, array_flip([
            'quantity', 'grade', 'temperature', 'condition_notes', 'loader_count', 'unloading_notes',
            'shortage_qty', 'damage_qty', 'accepted_qty', 'inspection_notes', 'booking_id', 'weighment_id',
        ]));

        if (! empty($data['submit'])) {
            $updates['status'] = 'pending';
        }

        $receipt->update($updates);

        $this->audit->record('grn.updated', $receipt, $before, $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => $receipt->only(array_keys($before)),
        ]);

        return $receipt->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function editApproved(User $actor, GoodsReceipt $receipt, array $data, string $reason): GoodsReceipt
    {
        abort_unless($this->canEditApproved($actor), 403);
        abort_unless($receipt->status === 'approved', 422);
        $this->guardReceipt($actor, $receipt);
        $this->reasons->assert('edit_after_approval', $reason);

        $before = $receipt->only([
            'quantity', 'grade', 'temperature', 'condition_notes', 'loader_count', 'unloading_notes',
            'shortage_qty', 'damage_qty', 'accepted_qty', 'inspection_notes',
        ]);

        $updates = array_intersect_key($data, array_flip([
            'quantity', 'grade', 'temperature', 'condition_notes', 'loader_count', 'unloading_notes',
            'shortage_qty', 'damage_qty', 'accepted_qty', 'inspection_notes',
        ]));
        $updates['last_edit_reason'] = $reason;
        $receipt->update($updates);

        if ($receipt->lot && isset($updates['accepted_qty'])) {
            $receipt->lot->update(['quantity' => $updates['accepted_qty']]);
        } elseif ($receipt->lot && isset($updates['quantity'])) {
            $receipt->lot->update(['quantity' => $updates['quantity']]);
        }

        $this->audit->record('grn.edited_after_approval', $receipt, $before, $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'reason' => $reason,
            'new' => $receipt->only(array_keys($before)),
        ]);

        return $receipt->fresh();
    }

    public function proposeOutcome(User $actor, GoodsReceipt $receipt, string $outcome, ?float $acceptedQty = null, ?string $notes = null): GoodsReceipt
    {
        abort_unless($this->canProposeOutcome($actor) || $this->canConfirmOutcome($actor), 403);
        abort_unless($receipt->isEditableDraft(), 422);
        abort_unless(in_array($outcome, ['accept', 'partial', 'reject'], true), 422);
        $this->guardReceipt($actor, $receipt);

        if ($this->canConfirmOutcome($actor) && ! $actor->hasRole(RoleName::Supervisor)) {
            return $this->confirmOutcome($actor, $receipt, $outcome, $acceptedQty, $notes);
        }

        $accepted = $this->resolveAcceptedQty($receipt, $outcome, $acceptedQty);
        $receipt->update([
            'proposed_outcome' => $outcome,
            'accepted_qty' => $accepted,
            'outcome_proposed_by' => $actor->id,
            'inspection_notes' => $notes ?? $receipt->inspection_notes,
            'status' => 'pending',
        ]);

        $this->audit->record('grn.outcome_proposed', $receipt, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => ['proposed_outcome' => $outcome, 'accepted_qty' => $accepted],
        ]);

        return $receipt->fresh();
    }

    public function confirmOutcome(User $actor, GoodsReceipt $receipt, string $outcome, ?float $acceptedQty = null, ?string $notes = null): GoodsReceipt
    {
        abort_unless($this->canConfirmOutcome($actor), 403);
        abort_unless($receipt->isEditableDraft() || $receipt->status === 'pending', 422);
        abort_unless(in_array($outcome, ['accept', 'partial', 'reject'], true), 422);
        $this->guardReceipt($actor, $receipt);

        $accepted = $this->resolveAcceptedQty($receipt, $outcome, $acceptedQty);
        $receipt->update([
            'outcome' => $outcome,
            'proposed_outcome' => $outcome,
            'accepted_qty' => $accepted,
            'outcome_approved_by' => $actor->id,
            'inspection_notes' => $notes ?? $receipt->inspection_notes,
            'status' => $outcome === 'reject' ? 'rejected' : 'pending',
        ]);

        $this->audit->record('grn.outcome_confirmed', $receipt, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => ['outcome' => $outcome, 'accepted_qty' => $accepted],
        ]);

        return $receipt->fresh();
    }

    public function uploadPhoto(User $actor, GoodsReceipt $receipt, UploadedFile $file, string $kind = 'damage', ?string $caption = null): GrnPhoto
    {
        abort_unless($this->canCreate($actor) || $this->canEditDraft($actor, $receipt) || $this->canEditApproved($actor), 403);
        abort_unless(in_array($kind, ['damage', 'shortage', 'other'], true), 422);
        $this->guardReceipt($actor, $receipt);
        if ($receipt->status === 'approved') {
            abort_unless($this->canEditApproved($actor), 403);
        } elseif (! $receipt->isEditableDraft()) {
            abort(422, 'Photos can only be added to draft or approved GRNs with edit rights.');
        }

        $path = $file->store('grn-photos/'.$receipt->tenant_id, 'public');
        $photo = GrnPhoto::query()->create([
            'tenant_id' => $receipt->tenant_id,
            'branch_id' => $receipt->branch_id,
            'goods_receipt_id' => $receipt->id,
            'path' => $path,
            'kind' => $kind,
            'caption' => $caption,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('grn.photo_uploaded', $photo, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => ['kind' => $kind, 'path' => $path],
        ]);

        return $photo;
    }

    public function approve(User $actor, GoodsReceipt $receipt, Chamber $chamber, ?Slot $suggestedSlot = null): StorageLot
    {
        abort_unless($this->canApprove($actor), 403);
        $this->guardReceipt($actor, $receipt);
        abort_unless((int) $chamber->branch_id === (int) $receipt->branch_id, 404);

        if ($receipt->status !== 'pending' && ! ($receipt->status === 'draft' && $receipt->outcome)) {
            throw ValidationException::withMessages(['status' => 'This GRN is not waiting for approval.']);
        }
        if ($receipt->outcome === 'reject' || $receipt->status === 'rejected') {
            throw ValidationException::withMessages(['outcome' => 'Rejected GRNs cannot be approved for put-away.']);
        }

        $this->makerChecker->assertCanApprove($actor, $receipt->created_by);

        if (! $receipt->outcome) {
            $outcome = $receipt->proposed_outcome ?: 'accept';
            $this->confirmOutcome($actor, $receipt, $outcome, $receipt->accepted_qty ? (float) $receipt->accepted_qty : null);
            $receipt->refresh();
        }

        return DB::transaction(function () use ($actor, $receipt, $chamber, $suggestedSlot) {
            $qty = (float) ($receipt->accepted_qty ?? $receipt->quantity);
            $receipt->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'chamber_id' => $chamber->id,
            ]);

            $lotNumber = $this->nextLotNumber($receipt->tenant_id, $receipt->branch_id);
            $lot = StorageLot::query()->create([
                'tenant_id' => $receipt->tenant_id,
                'branch_id' => $receipt->branch_id,
                'goods_receipt_id' => $receipt->id,
                'chamber_id' => $chamber->id,
                'slot_id' => null,
                'customer_id' => $receipt->customer_id,
                'commodity_id' => $receipt->commodity_id,
                'lot_number' => $lotNumber,
                'barcode' => $lotNumber,
                'quantity' => $qty,
                'status' => 'awaiting_putaway',
            ]);

            $slot = $suggestedSlot;
            if ($slot) {
                abort_unless((int) $slot->branch_id === (int) $receipt->branch_id, 404);
                abort_unless((int) $slot->chamber_id === (int) $chamber->id, 422);
                abort_unless($slot->availableForStorage(), 422);
            } else {
                $slot = Slot::query()
                    ->where('chamber_id', $chamber->id)
                    ->where('status', 'available')
                    ->orderBy('id')
                    ->first();
            }

            PutAwayTask::query()->create([
                'tenant_id' => $receipt->tenant_id,
                'branch_id' => $receipt->branch_id,
                'goods_receipt_id' => $receipt->id,
                'storage_lot_id' => $lot->id,
                'chamber_id' => $chamber->id,
                'suggested_slot_id' => $slot?->id,
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);

            $this->audit->record('grn.approved', $receipt, [], $receipt->tenant_id, [
                'module' => 'grn',
                'branch_id' => $receipt->branch_id,
                'new' => ['status' => 'approved', 'lot_id' => $lot->id, 'lot_number' => $lotNumber],
            ]);

            return $lot;
        });
    }

    public function createPutAwayTask(User $actor, StorageLot $lot, ?Slot $suggestedSlot = null): PutAwayTask
    {
        abort_unless($this->canCreatePutAway($actor), 403);
        $branch = Branch::query()->findOrFail($lot->branch_id);
        $this->guard($actor, $branch);
        abort_unless($lot->status === 'awaiting_putaway', 422);
        abort_unless(! PutAwayTask::query()->where('storage_lot_id', $lot->id)->where('status', 'pending')->exists(), 422);

        if ($suggestedSlot) {
            abort_unless((int) $suggestedSlot->chamber_id === (int) $lot->chamber_id, 422);
            abort_unless($suggestedSlot->availableForStorage(), 422);
        }

        $task = PutAwayTask::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'goods_receipt_id' => $lot->goods_receipt_id,
            'storage_lot_id' => $lot->id,
            'chamber_id' => $lot->chamber_id,
            'suggested_slot_id' => $suggestedSlot?->id,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('putaway.created', $task, [], $lot->tenant_id, [
            'module' => 'receiving',
            'branch_id' => $lot->branch_id,
            'new' => ['lot_id' => $lot->id],
        ]);

        return $task;
    }

    public function executePutAway(User $actor, PutAwayTask $task, Slot $slot, ?string $overrideReason = null): PutAwayTask
    {
        abort_unless($this->canPutAway($actor), 403);
        $this->guard($actor, $task->branch);
        abort_unless($task->status === 'pending', 422);
        abort_unless((int) $slot->branch_id === (int) $task->branch_id, 404);
        abort_unless((int) $slot->chamber_id === (int) $task->chamber_id, 422);
        abort_unless($slot->availableForStorage(), 422);

        $isOverride = $task->suggested_slot_id && (int) $task->suggested_slot_id !== (int) $slot->id;
        if ($isOverride && $actor->hasRole(RoleName::Supervisor) && ! $actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
            $this->reasons->assert('override', $overrideReason);
        }

        return DB::transaction(function () use ($actor, $task, $slot, $overrideReason, $isOverride) {
            $task->update([
                'slot_id' => $slot->id,
                'status' => 'completed',
                'override_reason' => $isOverride ? $overrideReason : null,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            $lot = $task->lot;
            $lot->update([
                'slot_id' => $slot->id,
                'status' => 'stored',
                'received_at' => now(),
            ]);

            $slot->update(['status' => 'occupied']);
            $this->inventory->recordReceipt($lot->fresh(), $actor);

            $this->audit->record('putaway.completed', $task, [], $task->tenant_id, [
                'module' => 'receiving',
                'branch_id' => $task->branch_id,
                'reason' => $isOverride ? $overrideReason : null,
                'new' => ['slot_id' => $slot->id, 'lot_id' => $lot->id, 'override' => $isOverride],
            ]);

            return $task->fresh();
        });
    }

    public function cancel(User $actor, GoodsReceipt $receipt, string $reason): GoodsReceipt
    {
        abort_unless($this->canCancel($actor), 403);
        $this->guardReceipt($actor, $receipt);
        $this->reasons->assert('reversal', $reason);
        abort_unless(in_array($receipt->status, ['draft', 'pending', 'rejected', 'approved'], true), 422);

        if ($receipt->status === 'approved') {
            $lot = $receipt->lot;
            if ($lot && $lot->status === 'stored') {
                throw ValidationException::withMessages(['status' => 'Stored lots must be handled before cancelling an approved GRN.']);
            }
            if ($lot && $lot->status === 'awaiting_putaway') {
                PutAwayTask::query()->where('goods_receipt_id', $receipt->id)->where('status', 'pending')->update(['status' => 'cancelled']);
                $lot->update(['status' => 'cancelled']);
            }
        }

        $receipt->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ]);

        $this->audit->record('grn.cancelled', $receipt, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'reason' => $reason,
        ]);

        return $receipt->fresh();
    }

    public function delete(User $actor, GoodsReceipt $receipt, string $reason): void
    {
        abort_unless($this->canDelete($actor), 403);
        $this->guardReceipt($actor, $receipt);
        $this->reasons->assert('delete', $reason);

        $this->audit->record('grn.deleted', $receipt, ['reason' => $reason], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
        ]);

        $receipt->photos()->each(fn (GrnPhoto $photo) => $photo->delete());
        $receipt->putAwayTasks()->each(fn (PutAwayTask $task) => $task->delete());
        $receipt->lot?->delete();
        $receipt->delete();
    }

    public function sendToCustomer(User $actor, GoodsReceipt $receipt, string $channel = 'email'): void
    {
        abort_unless($this->canSend($actor), 403);
        $this->guardReceipt($actor, $receipt);
        abort_unless(in_array($receipt->status, ['approved', 'rejected'], true), 422);

        $body = sprintf(
            'GRN %s for %s — qty %s, outcome %s, status %s.',
            $receipt->number ?? '#'.$receipt->id,
            $receipt->customer?->name ?? 'customer',
            $receipt->accepted_qty ?? $receipt->quantity,
            $receipt->outcome ?? '—',
            $receipt->status,
        );

        $this->customers->message($actor, $receipt->customer, $channel, $body);
        $receipt->update(['sent_to_customer_at' => now()]);

        $this->audit->record('grn.sent_to_customer', $receipt, [], $receipt->tenant_id, [
            'module' => 'grn',
            'branch_id' => $receipt->branch_id,
            'new' => ['channel' => $channel],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function limitedPayload(GoodsReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'status' => $receipt->status,
            'vehicle_no' => $receipt->gateEntry?->vehicle_no,
            'quantity' => $receipt->quantity,
            'outcome' => $receipt->outcome,
            'branch_id' => $receipt->branch_id,
        ];
    }

    private function resolveWeighment(GateEntry $entry, mixed $weighmentId): ?Weighment
    {
        if ($weighmentId) {
            $weighment = Weighment::query()->findOrFail($weighmentId);
            abort_unless((int) $weighment->gate_entry_id === (int) $entry->id, 404);

            return $weighment;
        }

        return Weighment::query()
            ->where('gate_entry_id', $entry->id)
            ->whereNull('deleted_at')
            ->latest('id')
            ->first();
    }

    private function resolveAcceptedQty(GoodsReceipt $receipt, string $outcome, ?float $acceptedQty): float
    {
        return match ($outcome) {
            'accept' => (float) $receipt->quantity,
            'reject' => 0.0,
            'partial' => (function () use ($receipt, $acceptedQty) {
                if ($acceptedQty === null) {
                    throw ValidationException::withMessages(['accepted_qty' => 'Accepted quantity is required for partial accept.']);
                }
                if ($acceptedQty <= 0 || $acceptedQty > (float) $receipt->quantity) {
                    throw ValidationException::withMessages(['accepted_qty' => 'Accepted quantity must be between 0 and received quantity.']);
                }

                return $acceptedQty;
            })(),
            default => throw ValidationException::withMessages(['outcome' => 'Invalid outcome.']),
        };
    }

    private function nextGrnNumber(int $tenantId, int $branchId): string
    {
        $seq = GoodsReceipt::query()->where('tenant_id', $tenantId)->withTrashed()->count() + 1;

        return sprintf('GRN-B%d-%s-%04d', $branchId, now()->format('Ymd'), $seq);
    }

    private function nextLotNumber(int $tenantId, int $branchId): string
    {
        $seq = StorageLot::query()->where('tenant_id', $tenantId)->withTrashed()->count() + 1;

        return sprintf('LOT-B%d-%s-%04d', $branchId, now()->format('Ymd'), $seq);
    }
}
