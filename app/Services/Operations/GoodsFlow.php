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
use App\Services\Dispatch\DispatchDesk;
use App\Services\Billing\BillingDesk;
use App\Services\Monitoring\MonitoringDesk;
use App\Services\Accounts\AccountsDesk;
use App\Services\Receivables\ReceivablesDesk;
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
        private readonly DispatchDesk $dispatch,
        private readonly MonitoringDesk $monitoring,
        private readonly BillingDesk $billing,
        private readonly ReceivablesDesk $receivables,
        private readonly AccountsDesk $accounts,
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
        return $this->dispatch->open($actor, $lot, $quantity);
    }

    public function approveDelivery(User $actor, DeliveryOrder $order): void
    {
        $this->dispatch->approve($actor, $order);
    }

    public function issuePass(User $actor, DeliveryOrder $order): GatePass
    {
        return $this->dispatch->issuePass($actor, $order);
    }

    public function release(User $actor, GatePass $pass): void
    {
        $this->dispatch->verifyExit($actor, $pass);
    }

    public function postInvoice(User $actor, StorageInvoice $invoice): void
    {
        $this->billing->post($actor, $invoice);
    }

    public function postPayment(User $actor, StoragePayment $payment): void
    {
        $this->receivables->postDraft($actor, $payment);
    }

    public function approveExpense(User $actor, OperatingExpense $expense): void
    {
        $this->accounts->approveExpense($actor, $expense, true);
    }

    public function recordReading(User $actor, Chamber $chamber, float $temperature, ?float $humidity): SensorReading
    {
        return $this->monitoring->recordManualLog($actor, $chamber, $temperature, $humidity);
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
