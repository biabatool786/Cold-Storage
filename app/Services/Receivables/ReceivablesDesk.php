<?php

namespace App\Services\Receivables;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\BadDebtWriteOff;
use App\Models\Csms\Customer;
use App\Models\Csms\CustomerDeposit;
use App\Models\Csms\DepositRefundRequest;
use App\Models\Csms\PaymentAllocation;
use App\Models\Csms\PaymentReminder;
use App\Models\Csms\StockLoan;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\FinancialDocumentGuard;
use App\Services\Authorization\MakerChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivablesDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly FinancialDocumentGuard $financialDocuments,
    ) {}

    public function canView(User $user): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('payments.print');
    }

    public function canRecordPayment(User $user): bool
    {
        return $user->hasPermission('payments.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canAllocate(User $user): bool
    {
        return $user->hasPermission('payments.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canEditSameDay(User $user): bool
    {
        return $user->hasPermission('payments.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canManageCheque(User $user): bool
    {
        return $this->canEditSameDay($user);
    }

    public function canRequestReverse(User $user): bool
    {
        return $user->hasPermission('payments.edit') && $user->hasRole(RoleName::Accountant);
    }

    public function canApproveReverse(User $user): bool
    {
        return $user->hasPermission('payments.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canExecuteReverse(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('payments.execute');
    }

    public function canRequestRefund(User $user): bool
    {
        return $user->hasPermission('payments.create') && $user->hasRole(RoleName::Accountant);
    }

    public function canApproveRefund(User $user): bool
    {
        return $user->hasPermission('payments.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canExecuteRefund(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('payments.execute');
    }

    public function canRemind(User $user): bool
    {
        return $user->hasPermission('payments.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function canHoldForDues(User $user): bool
    {
        return $user->hasPermission('customers.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canRequestWriteOff(User $user): bool
    {
        return $user->hasPermission('payments.create') && $user->hasRole(RoleName::Accountant);
    }

    public function canApproveWriteOff(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('payments.approve');
    }

    public function canCreateLoan(User $user): bool
    {
        return $user->hasPermission('payments.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveLoan(User $user): bool
    {
        return $user->hasPermission('payments.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canRequestLoanClose(User $user): bool
    {
        return $user->hasPermission('payments.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveLoanClose(User $user): bool
    {
        return $user->hasPermission('payments.approve') && $user->hasRole(RoleName::Manager);
    }

    public function canExecuteLoanClose(User $user): bool
    {
        return $user->hasPermission('payments.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        $branch = Branch::query()->findOrFail($branchId);
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function invoiceOutstanding(StorageInvoice $invoice): float
    {
        if (! $invoice->isPosted() || $invoice->status === 'cancelled') {
            return 0.0;
        }
        $applied = $this->appliedAmountForInvoice((int) $invoice->id);
        $writtenOff = (float) BadDebtWriteOff::query()
            ->where('storage_invoice_id', $invoice->id)
            ->where('status', 'executed')
            ->sum('amount');

        return max(0, round($invoice->netAmount() - $applied - $writtenOff, 2));
    }

    public function customerOutstanding(int $customerId): float
    {
        $total = 0.0;
        StorageInvoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', ['posted', 'locked'])
            ->each(function (StorageInvoice $invoice) use (&$total): void {
                $total += $this->invoiceOutstanding($invoice);
            });

        return round($total, 2);
    }

    public function paymentsCleared(Customer $customer): bool
    {
        return $this->customerOutstanding((int) $customer->id) <= 0.009;
    }

    public function appliedAmountForInvoice(int $invoiceId): float
    {
        $allocated = (float) PaymentAllocation::query()
            ->where('storage_invoice_id', $invoiceId)
            ->whereHas('payment', fn ($q) => $q->where('status', 'posted')
                ->whereNull('reversed_at')
                ->where(function ($q) {
                    $q->whereNull('reverse_status')->orWhere('reverse_status', '!=', 'executed');
                })
                ->where(function ($q) {
                    $q->where('method', '!=', 'cheque')
                        ->orWhere('cheque_status', 'cleared');
                }))
            ->sum('amount');

        if ($allocated > 0) {
            return round($allocated, 2);
        }

        // Legacy 1:1 payments without allocation rows.
        return round((float) StoragePayment::query()
            ->where('storage_invoice_id', $invoiceId)
            ->where('status', 'posted')
            ->whereNull('reversed_at')
            ->where(function ($q) {
                $q->whereNull('reverse_status')->orWhere('reverse_status', '!=', 'executed');
            })
            ->where(function ($q) {
                $q->where('method', '!=', 'cheque')->orWhere('cheque_status', 'cleared');
            })
            ->whereDoesntHave('allocations')
            ->sum('amount'), 2);
    }

    /**
     * @param  array{customer_id:int,amount:float,method:string,paid_on:string,reference?:?string,cheque_number?:?string,kind?:string,storage_invoice_id?:?int,notes?:?string,allocate?:bool}  $data
     */
    public function recordPayment(User $actor, Branch $branch, array $data): StoragePayment
    {
        abort_unless($this->canRecordPayment($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $method = $data['method'];
        abort_unless(in_array($method, ['cash', 'bank', 'cheque', 'wallet'], true), 422);
        $kind = $data['kind'] ?? 'invoice';
        abort_unless(in_array($kind, ['invoice', 'advance', 'security'], true), 422);
        $amount = round((float) $data['amount'], 2);
        abort_unless($amount > 0, 422);

        if (in_array($method, ['bank', 'cheque', 'wallet'], true) && empty($data['reference']) && empty($data['cheque_number'])) {
            throw ValidationException::withMessages(['reference' => 'Reference or cheque number is required for this method.']);
        }
        if ($method === 'cheque' && empty($data['cheque_number'])) {
            throw ValidationException::withMessages(['cheque_number' => 'Cheque number is required.']);
        }

        $reference = $data['reference'] ?? ($data['cheque_number'] ?? null);
        if ($reference !== null && $reference !== '') {
            $exists = StoragePayment::query()
                ->where('tenant_id', $branch->tenant_id)
                ->where('reference', $reference)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['reference' => 'Duplicate payment reference for this tenant.']);
            }
        }

        $customer = Customer::query()->findOrFail($data['customer_id']);
        abort_unless((int) $customer->tenant_id === (int) $branch->tenant_id, 422);

        $invoice = null;
        if (! empty($data['storage_invoice_id'])) {
            $invoice = StorageInvoice::query()->findOrFail($data['storage_invoice_id']);
            abort_unless($invoice->isPosted(), 422);
            abort_unless((int) $invoice->customer_id === (int) $customer->id, 422);
        }

        return DB::transaction(function () use ($actor, $branch, $data, $method, $kind, $amount, $reference, $customer, $invoice) {
            $payment = StoragePayment::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'storage_invoice_id' => $invoice?->id,
                'method' => $method,
                'reference' => $reference,
                'kind' => $kind === 'security' ? 'deposit' : $kind,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_status' => $method === 'cheque' ? 'deposited' : null,
                'cheque_status_at' => $method === 'cheque' ? now() : null,
                'amount' => $amount,
                'allocated_amount' => 0,
                'unapplied_amount' => $amount,
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if (in_array($kind, ['advance', 'security'], true)) {
                CustomerDeposit::query()->create([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'storage_payment_id' => $payment->id,
                    'kind' => $kind === 'advance' ? 'advance' : 'security',
                    'original_amount' => $amount,
                    'used_amount' => 0,
                    'refunded_amount' => 0,
                    'status' => 'active',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $actor->id,
                ]);
            }

            if ($invoice && ($data['allocate'] ?? true) && $kind === 'invoice' && $payment->isFundsEffective()) {
                $this->allocateInternal($actor, $payment, [(int) $invoice->id => min($amount, $this->invoiceOutstanding($invoice))]);
            } elseif ($invoice && $kind === 'invoice' && $method === 'cheque') {
                // Cheque not cleared yet — keep unapplied until cleared.
            }

            $this->audit->record('payment.recorded', $payment, [], $payment->tenant_id, [
                'module' => 'payments',
                'branch_id' => $payment->branch_id,
                'new' => ['amount' => $amount, 'method' => $method, 'kind' => $payment->kind],
            ]);

            return $payment->fresh(['allocations', 'customer']);
        });
    }

    /**
     * Legacy draft post path — posts draft and optionally allocates to linked invoice.
     */
    public function postDraft(User $actor, StoragePayment $payment): void
    {
        abort_unless($this->canAllocate($actor) || $this->canRecordPayment($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        $this->financialDocuments->assertMutable($payment->status);

        DB::transaction(function () use ($actor, $payment): void {
            $payment->update([
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'customer_id' => $payment->customer_id ?? $payment->invoice?->customer_id,
                'method' => $payment->method ?: 'cash',
                'kind' => $payment->kind ?: 'invoice',
                'paid_on' => $payment->paid_on ?? now()->toDateString(),
                'unapplied_amount' => $payment->unapplied_amount > 0 ? $payment->unapplied_amount : $payment->amount,
                'cheque_status' => $payment->method === 'cheque' ? ($payment->cheque_status ?: 'deposited') : $payment->cheque_status,
            ]);
            $payment->refresh();

            if ($payment->storage_invoice_id && $payment->isFundsEffective() && (float) $payment->allocated_amount < 0.01) {
                $invoice = StorageInvoice::query()->find($payment->storage_invoice_id);
                if ($invoice) {
                    $this->allocateInternal($actor, $payment, [
                        (int) $invoice->id => min((float) $payment->amount, $this->invoiceOutstanding($invoice)),
                    ]);
                }
            }

            $this->maybeCloseLotAfterPayment($payment);
            $this->audit->record('payment.posted', $payment, [], $payment->tenant_id, [
                'module' => 'payments',
                'branch_id' => $payment->branch_id,
                'new' => ['status' => 'posted'],
            ]);
        });
    }

    /**
     * @param  array<int, float>  $allocations  invoice_id => amount
     */
    public function allocate(User $actor, StoragePayment $payment, array $allocations): void
    {
        abort_unless($this->canAllocate($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless($payment->isPosted() && ! $payment->isReversed(), 422);
        abort_unless($payment->isFundsEffective(), 422);

        DB::transaction(fn () => $this->allocateInternal($actor, $payment->fresh(), $allocations));
    }

    /**
     * @param  array<int, float>  $allocations
     */
    private function allocateInternal(User $actor, StoragePayment $payment, array $allocations): void
    {
        $payment = $payment->fresh();
        $available = $payment->availableToAllocate();
        $total = 0.0;

        foreach ($allocations as $invoiceId => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $invoice = StorageInvoice::query()->findOrFail($invoiceId);
            abort_unless($invoice->isPosted(), 422);
            abort_unless((int) $invoice->customer_id === (int) $payment->customer_id, 422);
            $outstanding = $this->invoiceOutstanding($invoice);
            if ($amount - $outstanding > 0.009) {
                throw ValidationException::withMessages(['allocations' => 'Allocation exceeds invoice outstanding.']);
            }
            $dup = PaymentAllocation::query()
                ->where('storage_payment_id', $payment->id)
                ->where('storage_invoice_id', $invoice->id)
                ->exists();
            if ($dup) {
                throw ValidationException::withMessages(['allocations' => 'Duplicate allocation to the same invoice.']);
            }
            $total += $amount;
        }

        if ($total - $available > 0.009) {
            throw ValidationException::withMessages(['allocations' => 'Allocation exceeds available payment balance.']);
        }

        foreach ($allocations as $invoiceId => $amount) {
            $amount = round((float) $amount, 2);
            if ($amount <= 0) {
                continue;
            }
            PaymentAllocation::query()->create([
                'tenant_id' => $payment->tenant_id,
                'branch_id' => $payment->branch_id,
                'storage_payment_id' => $payment->id,
                'storage_invoice_id' => $invoiceId,
                'amount' => $amount,
                'created_by' => $actor->id,
            ]);
        }

        $allocated = (float) PaymentAllocation::query()->where('storage_payment_id', $payment->id)->sum('amount');
        $payment->update([
            'allocated_amount' => $allocated,
            'unapplied_amount' => max(0, (float) $payment->amount - $allocated),
            'storage_invoice_id' => $payment->storage_invoice_id ?: array_key_first($allocations),
        ]);

        $this->maybeCloseLotAfterPayment($payment);
        $this->audit->record('payment.allocated', $payment, [], $payment->tenant_id, [
            'module' => 'payments',
            'branch_id' => $payment->branch_id,
            'new' => ['allocations' => $allocations, 'allocated_amount' => $allocated],
        ]);
    }

    public function editSameDay(User $actor, StoragePayment $payment, array $data): void
    {
        abort_unless($this->canEditSameDay($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless($payment->isPosted() && ! $payment->isReversed(), 422);
        $paidOn = $payment->paid_on ? Carbon::parse($payment->paid_on)->toDateString() : Carbon::parse($payment->created_at)->toDateString();
        abort_unless($paidOn === now()->toDateString(), 422);

        $old = $payment->only(['notes', 'reference']);
        $payment->update([
            'notes' => $data['notes'] ?? $payment->notes,
            'reference' => $data['reference'] ?? $payment->reference,
        ]);
        $this->audit->record('payment.edited_same_day', $payment, $old, $payment->tenant_id, [
            'module' => 'payments',
            'branch_id' => $payment->branch_id,
            'new' => $payment->only(['notes', 'reference']),
        ]);
    }

    public function updateChequeStatus(User $actor, StoragePayment $payment, string $status): void
    {
        abort_unless($this->canManageCheque($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless($payment->method === 'cheque' && $payment->isPosted() && ! $payment->isReversed(), 422);
        abort_unless(in_array($status, ['deposited', 'cleared', 'bounced'], true), 422);

        $from = $payment->cheque_status;
        DB::transaction(function () use ($actor, $payment, $status, $from): void {
            $payment->update(['cheque_status' => $status, 'cheque_status_at' => now()]);

            if ($status === 'bounced') {
                // Bounced funds are not cleared — allocations no longer count via isFundsEffective.
                $this->audit->record('payment.cheque_bounced', $payment, [], $payment->tenant_id, [
                    'module' => 'payments',
                    'branch_id' => $payment->branch_id,
                    'old' => ['cheque_status' => $from],
                    'new' => ['cheque_status' => $status],
                ]);

                return;
            }

            if ($status === 'cleared' && $payment->storage_invoice_id && (float) $payment->allocated_amount < 0.01) {
                $invoice = StorageInvoice::query()->find($payment->storage_invoice_id);
                if ($invoice) {
                    $this->allocateInternal($actor, $payment->fresh(), [
                        (int) $invoice->id => min((float) $payment->amount, $this->invoiceOutstanding($invoice)),
                    ]);
                }
            }

            $this->audit->record('payment.cheque_'.$status, $payment, [], $payment->tenant_id, [
                'module' => 'payments',
                'branch_id' => $payment->branch_id,
                'old' => ['cheque_status' => $from],
                'new' => ['cheque_status' => $status],
            ]);
        });
    }

    public function requestReverse(User $actor, StoragePayment $payment, string $reason): void
    {
        abort_unless($this->canRequestReverse($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless($payment->isPosted() && ! $payment->isReversed(), 422);
        abort_unless($payment->reverse_status === null || $payment->reverse_status === 'rejected', 422);

        $payment->update([
            'reverse_status' => 'pending',
            'reverse_reason' => $reason,
            'reverse_requested_by' => $actor->id,
            'reverse_approved_by' => null,
        ]);
        $this->audit->record('payment.reverse_requested', $payment, [], $payment->tenant_id, [
            'module' => 'payments', 'branch_id' => $payment->branch_id, 'reason' => $reason,
        ]);
    }

    public function approveReverse(User $actor, StoragePayment $payment, bool $approve): void
    {
        abort_unless($this->canApproveReverse($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless($payment->reverse_status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $payment->reverse_requested_by);

        if (! $approve) {
            $payment->update(['reverse_status' => 'rejected']);
            $this->audit->record('payment.reverse_rejected', $payment, [], $payment->tenant_id, [
                'module' => 'payments', 'branch_id' => $payment->branch_id,
            ]);

            return;
        }

        $payment->update(['reverse_status' => 'approved', 'reverse_approved_by' => $actor->id]);
        $this->audit->record('payment.reverse_approved', $payment, [], $payment->tenant_id, [
            'module' => 'payments', 'branch_id' => $payment->branch_id,
        ]);

        if ($this->canExecuteReverse($actor)) {
            $this->executeReverse($actor, $payment->fresh());
        }
    }

    public function executeReverse(User $actor, StoragePayment $payment): void
    {
        abort_unless($this->canExecuteReverse($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        abort_unless(in_array($payment->reverse_status, ['approved', 'pending'], true) || $actor->isSuperAdmin(), 422);
        if ($payment->reverse_status === 'pending' && $actor->isSuperAdmin()) {
            // SA may execute after Manager approved only — unless SA also approved as manager path.
            abort_unless($payment->reverse_approved_by !== null || $this->canApproveReverse($actor), 422);
            if ($payment->reverse_approved_by === null) {
                $this->makerChecker->assertCanApprove($actor, $payment->reverse_requested_by);
                $payment->update(['reverse_status' => 'approved', 'reverse_approved_by' => $actor->id]);
            }
        }
        abort_unless($payment->fresh()->reverse_status === 'approved', 422);

        DB::transaction(function () use ($actor, $payment): void {
            $payment->update([
                'reverse_status' => 'executed',
                'reversed_at' => now(),
            ]);
            $this->audit->record('payment.reversed', $payment, [], $payment->tenant_id, [
                'module' => 'payments',
                'branch_id' => $payment->branch_id,
                'reason' => $payment->reverse_reason,
                'new' => ['reversed_at' => now()->toIso8601String()],
            ]);
        });
    }

    public function requestDepositRefund(User $actor, CustomerDeposit $deposit, float $amount, string $reason): DepositRefundRequest
    {
        abort_unless($this->canRequestRefund($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $deposit->branch_id);
        $amount = round($amount, 2);
        abort_unless($amount > 0 && $amount <= $deposit->remainingBalance() + 0.009, 422);

        $req = DepositRefundRequest::query()->create([
            'tenant_id' => $deposit->tenant_id,
            'branch_id' => $deposit->branch_id,
            'customer_deposit_id' => $deposit->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $actor->id,
        ]);
        $this->audit->record('deposit.refund_requested', $req, [], $deposit->tenant_id, [
            'module' => 'payments', 'branch_id' => $deposit->branch_id, 'reason' => $reason,
        ]);

        return $req;
    }

    public function approveDepositRefund(User $actor, DepositRefundRequest $request, bool $approve): void
    {
        abort_unless($this->canApproveRefund($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $request->branch_id);
        abort_unless($request->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $request->requested_by);

        if (! $approve) {
            $request->update(['status' => 'rejected']);
            $this->audit->record('deposit.refund_rejected', $request, [], $request->tenant_id, [
                'module' => 'payments', 'branch_id' => $request->branch_id,
            ]);

            return;
        }

        $request->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->audit->record('deposit.refund_approved', $request, [], $request->tenant_id, [
            'module' => 'payments', 'branch_id' => $request->branch_id,
        ]);
    }

    public function executeDepositRefund(User $actor, DepositRefundRequest $request): void
    {
        abort_unless($this->canExecuteRefund($actor), 403);
        $this->assertBranchAccess($actor, (int) $request->branch_id);
        abort_unless($request->status === 'approved', 422);

        DB::transaction(function () use ($actor, $request): void {
            $deposit = CustomerDeposit::query()->lockForUpdate()->findOrFail($request->customer_deposit_id);
            abort_unless((float) $request->amount <= $deposit->remainingBalance() + 0.009, 422);

            StoragePayment::query()->create([
                'tenant_id' => $deposit->tenant_id,
                'branch_id' => $deposit->branch_id,
                'customer_id' => $deposit->customer_id,
                'method' => 'cash',
                'kind' => 'refund',
                'paid_on' => now()->toDateString(),
                'amount' => $request->amount,
                'allocated_amount' => 0,
                'unapplied_amount' => 0,
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'notes' => 'Deposit refund #'.$request->id,
                'created_by' => $actor->id,
            ]);

            $newRefunded = (float) $deposit->refunded_amount + (float) $request->amount;
            $remaining = (float) $deposit->original_amount - (float) $deposit->used_amount - $newRefunded;
            $deposit->update([
                'refunded_amount' => $newRefunded,
                'status' => $remaining <= 0.009 ? 'closed' : 'active',
            ]);

            $request->update(['status' => 'executed', 'executed_by' => $actor->id, 'executed_at' => now()]);
            $this->audit->record('deposit.refund_executed', $request, [], $request->tenant_id, [
                'module' => 'payments', 'branch_id' => $request->branch_id,
            ]);
        });
    }

    public function useDeposit(User $actor, CustomerDeposit $deposit, StorageInvoice $invoice, float $amount): void
    {
        abort_unless($this->canAllocate($actor), 403);
        $this->assertBranchAccess($actor, (int) $deposit->branch_id);
        $amount = round($amount, 2);
        abort_unless($amount > 0 && $amount <= $deposit->remainingBalance() + 0.009, 422);
        abort_unless((int) $invoice->customer_id === (int) $deposit->customer_id, 422);
        abort_unless($amount <= $this->invoiceOutstanding($invoice) + 0.009, 422);

        DB::transaction(function () use ($actor, $deposit, $invoice, $amount): void {
            $payment = StoragePayment::query()->create([
                'tenant_id' => $deposit->tenant_id,
                'branch_id' => $deposit->branch_id,
                'customer_id' => $deposit->customer_id,
                'storage_invoice_id' => $invoice->id,
                'method' => 'cash',
                'kind' => 'invoice',
                'paid_on' => now()->toDateString(),
                'amount' => $amount,
                'allocated_amount' => 0,
                'unapplied_amount' => $amount,
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'notes' => 'Applied from deposit #'.$deposit->id,
                'created_by' => $actor->id,
                'reference' => 'DEP-USE-'.$deposit->id.'-'.$invoice->id.'-'.time(),
            ]);
            $this->allocateInternal($actor, $payment, [(int) $invoice->id => $amount]);
            $deposit->update(['used_amount' => (float) $deposit->used_amount + $amount]);
            $deposit->refresh();
            if ($deposit->remainingBalance() <= 0.009) {
                $deposit->update(['status' => 'closed']);
            }
        });
    }

    public function requestWriteOff(User $actor, StorageInvoice $invoice, float $amount, string $reason): BadDebtWriteOff
    {
        abort_unless($this->canRequestWriteOff($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);
        $amount = round($amount, 2);
        abort_unless($invoice->isPosted(), 422);
        abort_unless($amount > 0 && $amount <= $this->invoiceOutstanding($invoice) + 0.009, 422);

        $row = BadDebtWriteOff::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'branch_id' => $invoice->branch_id,
            'customer_id' => $invoice->customer_id,
            'storage_invoice_id' => $invoice->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $actor->id,
        ]);
        $this->audit->record('payment.writeoff_requested', $row, [], $invoice->tenant_id, [
            'module' => 'payments', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
        ]);

        return $row;
    }

    public function approveWriteOff(User $actor, BadDebtWriteOff $writeOff, bool $approve): void
    {
        abort_unless($this->canApproveWriteOff($actor), 403);
        $this->assertBranchAccess($actor, (int) $writeOff->branch_id);
        abort_unless($writeOff->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $writeOff->requested_by);

        if (! $approve) {
            $writeOff->update(['status' => 'rejected']);
            $this->audit->record('payment.writeoff_rejected', $writeOff, [], $writeOff->tenant_id, [
                'module' => 'payments', 'branch_id' => $writeOff->branch_id,
            ]);

            return;
        }

        DB::transaction(function () use ($actor, $writeOff): void {
            $writeOff->update([
                'status' => 'executed',
                'approved_by' => $actor->id,
                'executed_at' => now(),
            ]);
            $this->audit->record('payment.writeoff_executed', $writeOff, [], $writeOff->tenant_id, [
                'module' => 'payments', 'branch_id' => $writeOff->branch_id, 'reason' => $writeOff->reason,
            ]);
        });
    }

    public function createLoan(User $actor, Branch $branch, Customer $customer, StorageLot $lot, float $amount, ?string $notes): StockLoan
    {
        abort_unless($this->canCreateLoan($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        abort_unless((int) $lot->customer_id === (int) $customer->id, 422);
        $amount = round($amount, 2);
        abort_unless($amount > 0, 422);

        $loan = StockLoan::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'storage_lot_id' => $lot->id,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('loan.created', $loan, [], $branch->tenant_id, [
            'module' => 'payments', 'branch_id' => $branch->id,
        ]);

        return $loan;
    }

    public function approveLoan(User $actor, StockLoan $loan, bool $approve): void
    {
        abort_unless($this->canApproveLoan($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $loan->branch_id);
        abort_unless($loan->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $loan->created_by);

        if (! $approve) {
            $loan->update(['status' => 'rejected']);
            $this->audit->record('loan.rejected', $loan, [], $loan->tenant_id, [
                'module' => 'payments', 'branch_id' => $loan->branch_id,
            ]);

            return;
        }

        $loan->update(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->audit->record('loan.approved', $loan, [], $loan->tenant_id, [
            'module' => 'payments', 'branch_id' => $loan->branch_id,
        ]);
    }

    public function requestLoanClose(User $actor, StockLoan $loan): void
    {
        abort_unless($this->canRequestLoanClose($actor), 403);
        $this->assertBranchAccess($actor, (int) $loan->branch_id);
        abort_unless($loan->status === 'active', 422);
        abort_unless((float) $loan->outstanding_amount <= 0.009, 422);

        $loan->update(['status' => 'close_pending', 'closed_by' => $actor->id]);
        $this->audit->record('loan.close_requested', $loan, [], $loan->tenant_id, [
            'module' => 'payments', 'branch_id' => $loan->branch_id,
        ]);
    }

    public function approveLoanClose(User $actor, StockLoan $loan, bool $approve): void
    {
        abort_unless($this->canApproveLoanClose($actor) || $actor->isSuperAdmin(), 403);
        $this->assertBranchAccess($actor, (int) $loan->branch_id);
        abort_unless($loan->status === 'close_pending', 422);
        $this->makerChecker->assertCanApprove($actor, $loan->closed_by);

        if (! $approve) {
            $loan->update(['status' => 'active', 'closed_by' => null]);
            $this->audit->record('loan.close_rejected', $loan, [], $loan->tenant_id, [
                'module' => 'payments', 'branch_id' => $loan->branch_id,
            ]);

            return;
        }

        $loan->update(['status' => 'close_approved', 'approved_by' => $loan->approved_by ?: $actor->id]);
        $this->audit->record('loan.close_approved', $loan, [], $loan->tenant_id, [
            'module' => 'payments', 'branch_id' => $loan->branch_id,
        ]);
    }

    public function executeLoanClose(User $actor, StockLoan $loan): void
    {
        abort_unless($this->canExecuteLoanClose($actor), 403);
        $this->assertBranchAccess($actor, (int) $loan->branch_id);
        abort_unless($loan->status === 'close_approved', 422);
        abort_unless((float) $loan->outstanding_amount <= 0.009, 422);

        $loan->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id]);
        $this->audit->record('loan.closed', $loan, [], $loan->tenant_id, [
            'module' => 'payments', 'branch_id' => $loan->branch_id,
        ]);
    }

    /**
     * Reduce outstanding when a repayment is recorded (no interest/schedule invented).
     */
    public function recordLoanRepayment(User $actor, StockLoan $loan, float $amount): void
    {
        abort_unless($this->canRecordPayment($actor), 403);
        $this->assertBranchAccess($actor, (int) $loan->branch_id);
        abort_unless($loan->status === 'active', 422);
        $amount = round($amount, 2);
        abort_unless($amount > 0 && $amount <= (float) $loan->outstanding_amount + 0.009, 422);

        $loan->update(['outstanding_amount' => max(0, (float) $loan->outstanding_amount - $amount)]);
        $this->audit->record('loan.repayment', $loan, [], $loan->tenant_id, [
            'module' => 'payments', 'branch_id' => $loan->branch_id, 'new' => ['amount' => $amount],
        ]);
    }

    public function sendReminder(User $actor, Customer $customer, ?string $message = null): array
    {
        abort_unless($this->canRemind($actor), 403);
        $this->assertBranchAccess($actor, (int) $customer->branch_id);

        $mailConfigured = filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.from.address'))
            && ! in_array(config('mail.default'), ['log', 'array'], true);

        $outcome = match (true) {
            ! filled($customer->email) => 'No customer email on file.',
            ! $mailConfigured => 'Outbound email is not configured in this environment.',
            default => 'Reminder accepted by mailer.',
        };
        $status = ($mailConfigured && filled($customer->email)) ? 'sent' : 'failed';

        $reminder = PaymentReminder::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'customer_id' => $customer->id,
            'channel' => 'email',
            'status' => $status,
            'message' => $message ?? 'Payment reminder for outstanding dues.',
            'outcome' => $outcome,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('payment.reminder_'.$status, $reminder, [], $customer->tenant_id, [
            'module' => 'payments', 'branch_id' => $customer->branch_id, 'reason' => $outcome,
        ]);

        return [
            'sent' => $status === 'sent',
            'message' => $status === 'sent' ? 'Payment reminder sent.' : 'Payment reminder was not sent: '.$outcome,
        ];
    }

    public function placeHold(User $actor, Customer $customer, string $reason): void
    {
        abort_unless($this->canHoldForDues($actor), 403);
        $this->assertBranchAccess($actor, (int) $customer->branch_id);
        abort_unless(strlen(trim($reason)) >= 3, 422);
        $customer->update(['status' => 'hold', 'hold_reason' => $reason, 'status_changed_at' => now()]);
        $this->audit->record('customer.dues_hold', $customer, [], $customer->tenant_id, [
            'module' => 'payments', 'branch_id' => $customer->branch_id, 'reason' => $reason,
        ]);
    }

    public function removeHold(User $actor, Customer $customer, string $reason): void
    {
        abort_unless($this->canHoldForDues($actor), 403);
        $this->assertBranchAccess($actor, (int) $customer->branch_id);
        abort_unless(strlen(trim($reason)) >= 3, 422);
        $customer->update(['status' => 'approved', 'hold_reason' => null, 'status_changed_at' => now()]);
        $this->audit->record('customer.dues_hold_removed', $customer, [], $customer->tenant_id, [
            'module' => 'payments', 'branch_id' => $customer->branch_id, 'reason' => $reason,
        ]);
    }

    /**
     * @return array{opening: float, lines: list<array<string, mixed>>, closing: float}
     */
    public function customerStatement(User $actor, Customer $customer): array
    {
        abort_unless($this->canView($actor), 403);
        $this->assertBranchAccess($actor, (int) $customer->branch_id);

        $lines = [];
        $balance = 0.0;

        $invoices = StorageInvoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['posted', 'locked', 'cancelled'])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            if ($invoice->status === 'cancelled') {
                continue;
            }
            $charge = $invoice->netAmount();
            $balance += $charge;
            $lines[] = [
                'date' => optional($invoice->invoice_date)->toDateString(),
                'type' => 'charge',
                'reference' => $invoice->number ?? 'INV-'.$invoice->id,
                'debit' => $charge,
                'credit' => 0,
                'balance' => round($balance, 2),
            ];
            $credits = (float) $invoice->credited_amount;
            if ($credits > 0) {
                $balance -= $credits;
                $lines[] = [
                    'date' => optional($invoice->invoice_date)->toDateString(),
                    'type' => 'credit',
                    'reference' => ($invoice->number ?? 'INV-'.$invoice->id).'-CN',
                    'debit' => 0,
                    'credit' => $credits,
                    'balance' => round($balance, 2),
                ];
            }
        }

        $payments = StoragePayment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'posted')
            ->whereNull('reversed_at')
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            if (! $payment->isFundsEffective() && $payment->kind !== 'refund') {
                continue;
            }
            if ($payment->kind === 'refund') {
                $balance += (float) $payment->amount;
                $lines[] = [
                    'date' => optional($payment->paid_on)->toDateString(),
                    'type' => 'refund',
                    'reference' => $payment->reference ?? 'PAY-'.$payment->id,
                    'debit' => (float) $payment->amount,
                    'credit' => 0,
                    'balance' => round($balance, 2),
                ];
                continue;
            }
            if (in_array($payment->kind, ['deposit', 'advance'], true)) {
                continue; // deposits tracked separately from AR charges
            }
            $credit = (float) $payment->allocated_amount > 0
                ? (float) $payment->allocated_amount
                : (float) $payment->amount;
            $balance -= $credit;
            $lines[] = [
                'date' => optional($payment->paid_on)->toDateString(),
                'type' => 'payment',
                'reference' => $payment->reference ?? 'PAY-'.$payment->id,
                'debit' => 0,
                'credit' => $credit,
                'balance' => round($balance, 2),
            ];
        }

        foreach (BadDebtWriteOff::query()->where('customer_id', $customer->id)->where('status', 'executed')->get() as $wo) {
            $balance -= (float) $wo->amount;
            $lines[] = [
                'date' => optional($wo->executed_at)->toDateString(),
                'type' => 'write_off',
                'reference' => 'WO-'.$wo->id,
                'debit' => 0,
                'credit' => (float) $wo->amount,
                'balance' => round($balance, 2),
            ];
        }

        return [
            'opening' => 0.0,
            'lines' => $lines,
            'closing' => round($this->customerOutstanding((int) $customer->id), 2),
        ];
    }

    /**
     * Aging buckets: current (not due), 1–30, 31–60, 61–90, 90+ (due_date based).
     *
     * @return list<array{customer_id:int,customer:string,current:float,d30:float,d60:float,d90:float,d90_plus:float,total:float}>
     */
    public function receivableAging(User $actor): array
    {
        abort_unless($this->canView($actor), 403);
        $today = now()->startOfDay();
        $rows = [];

        $invoices = StorageInvoice::query()
            ->visibleTo($actor)
            ->whereIn('status', ['posted', 'locked'])
            ->with('customer')
            ->get();

        foreach ($invoices as $invoice) {
            $outstanding = $this->invoiceOutstanding($invoice);
            if ($outstanding <= 0.009) {
                continue;
            }
            $due = $invoice->due_date ? Carbon::parse($invoice->due_date)->startOfDay() : Carbon::parse($invoice->invoice_date ?? $invoice->created_at)->startOfDay();
            $days = $due->greaterThan($today) ? 0 : $due->diffInDays($today);
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => 'd30',
                $days <= 60 => 'd60',
                $days <= 90 => 'd90',
                default => 'd90_plus',
            };
            $cid = (int) $invoice->customer_id;
            if (! isset($rows[$cid])) {
                $rows[$cid] = [
                    'customer_id' => $cid,
                    'customer' => $invoice->customer?->name ?? '#'.$cid,
                    'current' => 0.0,
                    'd30' => 0.0,
                    'd60' => 0.0,
                    'd90' => 0.0,
                    'd90_plus' => 0.0,
                    'total' => 0.0,
                ];
            }
            $rows[$cid][$bucket] += $outstanding;
            $rows[$cid]['total'] += $outstanding;
        }

        return array_values($rows);
    }

    public function recordPrint(User $actor, StoragePayment $payment): void
    {
        abort_unless($this->canPrint($actor), 403);
        $this->assertBranchAccess($actor, (int) $payment->branch_id);
        $this->audit->record('payment.receipt_printed', $payment, [], $payment->tenant_id, [
            'module' => 'payments', 'branch_id' => $payment->branch_id,
        ]);
    }

    public function recordStatementExport(User $actor, Customer $customer): void
    {
        abort_unless($this->canPrint($actor), 403);
        $this->assertBranchAccess($actor, (int) $customer->branch_id);
        $this->audit->record('payment.statement_exported', $customer, [], $customer->tenant_id, [
            'module' => 'payments', 'branch_id' => $customer->branch_id,
        ]);
    }

    public function recordAgingExport(User $actor): void
    {
        abort_unless($this->canPrint($actor), 403);
        $this->audit->record('payment.aging_exported', $actor, [], $actor->tenant_id, [
            'module' => 'payments',
        ]);
    }

    public function customerOnDuesHold(Customer $customer): bool
    {
        return in_array($customer->status, ['hold', 'blacklisted'], true);
    }

    private function maybeCloseLotAfterPayment(StoragePayment $payment): void
    {
        $invoice = $payment->storage_invoice_id
            ? StorageInvoice::query()->find($payment->storage_invoice_id)
            : null;
        if (! $invoice || ! $invoice->delivery_order_id) {
            return;
        }
        if ($this->invoiceOutstanding($invoice) > 0.009) {
            return;
        }
        $lotId = \App\Models\Csms\DeliveryOrder::query()->whereKey($invoice->delivery_order_id)->value('storage_lot_id');
        if ($lotId) {
            StorageLot::query()->whereKey($lotId)->update(['status' => 'closed']);
        }
    }
}
