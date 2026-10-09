<?php

namespace App\Services\Operations;

use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Chamber;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GatePass;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\Inspection;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\SensorReading;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Models\Csms\Weighment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\FinancialDocumentGuard;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Receiving\ReceivingDesk;
use App\Services\Weighbridge\WeighbridgeDesk;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class GoodsFlow
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
        private readonly FinancialDocumentGuard $financialDocuments,
        private readonly ReasonRequirement $reasons,
        private readonly BranchAccess $branchAccess,
        private readonly WeighbridgeDesk $weighbridge,
        private readonly ReceivingDesk $receiving,
    ) {}

    public function branchFor(User $actor, Branch $branch): Branch
    {
        if (! $this->branchAccess->allows($actor, $branch)) {
            throw new AuthorizationException('This branch is not assigned to you.');
        }

        return $branch;
    }

    public function stamp(Model $record, User $actor): void
    {
        if ($record->created_by === null) {
            $record->created_by = $actor->id;
        }
    }

    public function approveBooking(User $actor, Booking $booking): void
    {
        $this->guardPending($actor, $booking, 'bookings.approve');
        $booking->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->record('booking.approved', $booking, [], $booking->tenant_id, [
            'module' => 'bookings',
            'branch_id' => $booking->branch_id,
            'new' => ['status' => 'approved'],
        ]);
    }

    public function recordWeight(User $actor, GateEntry $entry, string $kind, float $weight, bool $manual = false, ?string $reason = null): Weighment
    {
        return $this->weighbridge->record($actor, $entry, $kind, $weight, $manual, $reason);
    }

    public function inspect(User $actor, GateEntry $entry, string $result, ?string $notes): Inspection
    {
        abort_unless($actor->hasPermission('grn.create') || $actor->hasPermission('quality.create'), 403);
        $this->ownsBranch($actor, $entry);

        $inspection = Inspection::query()->create([
            'tenant_id' => $entry->tenant_id,
            'branch_id' => $entry->branch_id,
            'gate_entry_id' => $entry->id,
            'result' => $result,
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('inspection.recorded', $inspection, [], $entry->tenant_id, [
            'module' => 'quality',
            'branch_id' => $entry->branch_id,
            'new' => ['result' => $result],
        ]);

        return $inspection;
    }

    public function approveReceipt(User $actor, GoodsReceipt $receipt, Chamber $chamber): StorageLot
    {
        return $this->receiving->approve($actor, $receipt, $chamber);
    }

    public function openDelivery(User $actor, StorageLot $lot, float $quantity): DeliveryOrder
    {
        abort_unless($actor->hasPermission('dispatch.create'), 403);
        $this->ownsBranch($actor, $lot);
        abort_unless($lot->status === 'stored', 403);

        $dues = (float) StorageInvoice::query()
            ->where('customer_id', $lot->customer_id)
            ->where('status', 'posted')
            ->sum('amount')
            - (float) StoragePayment::query()
                ->where('status', 'posted')
                ->whereIn('storage_invoice_id', StorageInvoice::query()->where('customer_id', $lot->customer_id)->pluck('id'))
                ->sum('amount');

        $order = DeliveryOrder::query()->create([
            'tenant_id' => $lot->tenant_id,
            'branch_id' => $lot->branch_id,
            'storage_lot_id' => $lot->id,
            'customer_id' => $lot->customer_id,
            'quantity' => $quantity,
            'dues_amount' => max(0, $dues),
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('delivery.opened', $order, [], $lot->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $lot->branch_id,
            'new' => ['dues_amount' => $order->dues_amount],
        ]);

        return $order;
    }

    public function approveDelivery(User $actor, DeliveryOrder $order): void
    {
        $this->guardPending($actor, $order, 'dispatch.approve');
        if ((float) $order->dues_amount > 0) {
            $this->limits->assertCanApprove($actor, 'delivery_order_overdue', $order->dues_amount);
        }
        $order->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->record('delivery.approved', $order, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
            'new' => ['status' => 'approved'],
        ]);
    }

    public function issuePass(User $actor, DeliveryOrder $order): GatePass
    {
        abort_unless($actor->hasPermission('dispatch.execute'), 403);
        abort_unless($order->status === 'approved', 403);
        $this->ownsBranch($actor, $order);

        $order->update(['status' => 'loaded']);
        $pass = GatePass::query()->create([
            'tenant_id' => $order->tenant_id,
            'branch_id' => $order->branch_id,
            'delivery_order_id' => $order->id,
            'status' => 'issued',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('gate_pass.issued', $pass, [], $order->tenant_id, [
            'module' => 'dispatch',
            'branch_id' => $order->branch_id,
        ]);

        return $pass;
    }

    public function release(User $actor, GatePass $pass): void
    {
        abort_unless($actor->hasPermission('gate.execute'), 403);
        abort_unless($pass->status === 'issued', 403);
        $this->ownsBranch($actor, $pass);
        $pass->update(['status' => 'released', 'released_at' => now()]);
        $this->audit->record('gate.released', $pass, [], $pass->tenant_id, [
            'module' => 'gate',
            'branch_id' => $pass->branch_id,
            'new' => ['status' => 'released'],
        ]);
    }

    public function postInvoice(User $actor, StorageInvoice $invoice): void
    {
        abort_unless($actor->hasPermission('billing.execute'), 403);
        $this->financialDocuments->assertMutable($invoice->status);
        $invoice->update(['status' => 'posted']);
        $this->audit->record('invoice.posted', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing',
            'branch_id' => $invoice->branch_id,
            'new' => ['status' => 'posted'],
        ]);
    }

    public function postPayment(User $actor, StoragePayment $payment): void
    {
        abort_unless($actor->hasPermission('payments.execute'), 403);
        $this->financialDocuments->assertMutable($payment->status);
        $payment->update(['status' => 'posted']);
        $invoice = $payment->invoice;
        $paid = (float) $invoice->payments()->where('status', 'posted')->sum('amount');
        if ($invoice->status === 'posted' && $paid >= (float) $invoice->amount && $invoice->delivery_order_id) {
            $lotId = DeliveryOrder::query()->whereKey($invoice->delivery_order_id)->value('storage_lot_id');
            if ($lotId) {
                StorageLot::query()->whereKey($lotId)->update(['status' => 'closed']);
            }
        }
        $this->audit->record('payment.posted', $payment, [], $payment->tenant_id, [
            'module' => 'payments',
            'branch_id' => $payment->branch_id,
            'new' => ['status' => 'posted'],
        ]);
    }

    public function approveExpense(User $actor, OperatingExpense $expense): void
    {
        $this->guardPending($actor, $expense, 'expenses.approve');
        $this->limits->assertCanApprove($actor, 'expense', $expense->amount);
        $expense->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->record('expense.approved', $expense, [], $expense->tenant_id, [
            'module' => 'expenses',
            'branch_id' => $expense->branch_id,
        ]);
    }

    public function recordReading(User $actor, Chamber $chamber, float $temperature, ?float $humidity): SensorReading
    {
        abort_unless($actor->hasPermission('monitoring.create'), 403);
        $this->ownsBranch($actor, $chamber);

        $reading = SensorReading::query()->create([
            'tenant_id' => $chamber->tenant_id,
            'branch_id' => $chamber->branch_id,
            'chamber_id' => $chamber->id,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('sensor.recorded', $reading, [], $chamber->tenant_id, [
            'module' => 'monitoring',
            'branch_id' => $chamber->branch_id,
            'new' => ['temperature' => $temperature],
        ]);

        return $reading;
    }

    public function closeGate(User $actor, GateEntry $entry): void
    {
        abort_unless($actor->hasPermission('gate.execute'), 403);
        abort_unless($actor->isSuperAdmin() || $actor->hasRole(\App\Enums\RoleName::Gatekeeper), 403);
        $this->ownsBranch($actor, $entry);
        if ($entry->purpose === 'outbound' && $entry->pass_verified_at === null && $entry->override_approved_by === null) {
            abort(403, 'Outbound release needs a verified gate pass or an approved override.');
        }
        $entry->update(['status' => 'closed', 'exited_at' => now()]);
        $this->audit->record('gate.closed', $entry, [], $entry->tenant_id, [
            'module' => 'gate',
            'branch_id' => $entry->branch_id,
        ]);
    }

    public function requireReason(string $action, ?string $reason): void
    {
        $this->reasons->assert($action, $reason);
    }

    private function guardPending(User $actor, Model $record, string $permission): void
    {
        abort_unless($actor->hasPermission($permission), 403);
        $this->ownsBranch($actor, $record);
        if ($record->getAttribute('status') !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This record is not waiting for approval.']);
        }
        $this->makerChecker->assertCanApprove($actor, $record->getAttribute('created_by'));
    }

    private function ownsBranch(User $actor, Model $record): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        $branch = Branch::query()->find($record->getAttribute('branch_id'));
        if (! $branch || ! $this->branchAccess->allows($actor, $branch)) {
            throw new AuthorizationException('This branch is not assigned to you.');
        }
    }
}
