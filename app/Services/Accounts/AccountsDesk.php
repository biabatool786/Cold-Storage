<?php

namespace App\Services\Accounts;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\AccountingExport;
use App\Models\Csms\AccountingPeriod;
use App\Models\Csms\BankReconciliation;
use App\Models\Csms\BankStatementLine;
use App\Models\Csms\Chamber;
use App\Models\Csms\Customer;
use App\Models\Csms\ExpenseAttachment;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\PettyCashEntry;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StoragePayment;
use App\Models\Csms\Vendor;
use App\Models\Csms\VendorBill;
use App\Models\Csms\VendorPayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Notifications\OperationalAlertHooks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountsDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
        private readonly OperationalAlertHooks $operationalAlerts,
    ) {}

    public function canViewExpenses(User $user): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function canCreateExpense(User $user): bool
    {
        return $user->hasPermission('expenses.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant, RoleName::Supervisor]));
    }

    public function canAttach(User $user): bool
    {
        return $user->hasPermission('expenses.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Supervisor, RoleName::Accountant]));
    }

    public function canApproveExpense(User $user): bool
    {
        return $user->hasPermission('expenses.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canPostExpense(User $user): bool
    {
        return $user->hasPermission('expenses.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canCreateVendor(User $user): bool
    {
        return $user->hasPermission('vendors.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Accountant]));
    }

    public function canViewVendors(User $user): bool
    {
        return $user->hasPermission('vendors.view');
    }

    public function canRecordVendorBillPayment(User $user): bool
    {
        return $user->hasPermission('vendors.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canViewAccounts(User $user): bool
    {
        return $user->hasPermission('accounts.view');
    }

    public function canPrintAccounts(User $user): bool
    {
        return $user->hasPermission('accounts.print');
    }

    public function canExecuteAccounts(User $user): bool
    {
        return $user->hasPermission('accounts.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveAccounts(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('accounts.approve');
    }

    public function canViewPettyCash(User $user): bool
    {
        return $this->canViewAccounts($user)
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function canPostPettyCash(User $user): bool
    {
        return $user->hasPermission('accounts.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canViewDayBook(User $user): bool
    {
        return $this->canViewAccounts($user)
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Supervisor, RoleName::Accountant]));
    }

    public function canPrintDayBook(User $user): bool
    {
        return $this->canPrintAccounts($user)
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canViewProfitLoss(User $user): bool
    {
        return $this->canViewAccounts($user)
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function canPrintProfitLoss(User $user): bool
    {
        return $this->canPrintAccounts($user)
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Manager, RoleName::Accountant]));
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function assertPeriodOpen(int $tenantId, int $branchId, string $date): void
    {
        $closed = AccountingPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('status', 'closed')
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();
        if ($closed) {
            throw ValidationException::withMessages(['period' => 'This accounting period is closed.']);
        }
    }

    /**
     * @param  array{description:string,amount:float,category?:?string,expense_date:string,chamber_id?:?int,customer_id?:?int,vendor_id?:?int,payment_method?:?string,reference?:?string,kind?:string}  $data
     */
    public function createExpense(User $actor, Branch $branch, array $data): OperatingExpense
    {
        abort_unless($this->canCreateExpense($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $kind = $data['kind'] ?? 'expense';
        if ($actor->hasRole(RoleName::Supervisor) && ! $actor->isSuperAdmin()) {
            abort_unless($kind === 'petty_cash_request', 403);
        } else {
            abort_unless(in_array($kind, ['expense', 'petty_cash_request'], true), 422);
        }

        $amount = round((float) $data['amount'], 2);
        abort_unless($amount > 0, 422);
        $date = $data['expense_date'] ?? now()->toDateString();
        $this->assertPeriodOpen((int) $branch->tenant_id, (int) $branch->id, $date);

        $reference = $data['reference'] ?? null;
        if ($reference) {
            $dup = OperatingExpense::query()
                ->where('tenant_id', $branch->tenant_id)
                ->where('reference', $reference)
                ->exists();
            if ($dup) {
                throw ValidationException::withMessages(['reference' => 'Duplicate expense reference.']);
            }
        }

        // Soft duplicate guard: same branch/amount/date/description within pending.
        $softDup = OperatingExpense::query()
            ->where('branch_id', $branch->id)
            ->where('amount', $amount)
            ->whereDate('expense_date', $date)
            ->where('description', $data['description'])
            ->whereIn('status', ['pending', 'approved', 'posted'])
            ->exists();
        if ($softDup) {
            throw ValidationException::withMessages(['description' => 'A matching expense already exists for this date and amount.']);
        }

        $expense = OperatingExpense::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'kind' => $kind,
            'category' => $data['category'] ?? ($kind === 'petty_cash_request' ? 'petty_cash' : 'general'),
            'expense_date' => $date,
            'chamber_id' => $data['chamber_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'description' => $data['description'],
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $reference,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);

        $this->audit->record('expense.created', $expense, [], $expense->tenant_id, [
            'module' => 'expenses',
            'branch_id' => $expense->branch_id,
            'new' => ['amount' => $amount, 'kind' => $kind],
        ]);

        $this->operationalAlerts->pendingApproval(
            $expense,
            (int) $expense->tenant_id,
            (int) $expense->branch_id,
            'Expense',
            'Expense #'.$expense->id.' ('.$kind.') awaits approval.'
        );

        return $expense;
    }

    public function attachReceipt(User $actor, OperatingExpense $expense, UploadedFile $file): ExpenseAttachment
    {
        abort_unless($this->canAttach($actor), 403);
        $this->assertBranchAccess($actor, (int) $expense->branch_id);
        abort_unless(in_array($expense->status, ['pending', 'approved'], true), 422);

        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext, $allowed, true), 422);
        abort_unless($file->getSize() <= 5 * 1024 * 1024, 422);

        $path = $file->store('expense-receipts/'.$expense->tenant_id);
        $attachment = ExpenseAttachment::query()->create([
            'tenant_id' => $expense->tenant_id,
            'branch_id' => $expense->branch_id,
            'operating_expense_id' => $expense->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'created_by' => $actor->id,
        ]);
        $this->audit->record('expense.attachment_uploaded', $attachment, [], $expense->tenant_id, [
            'module' => 'expenses', 'branch_id' => $expense->branch_id,
        ]);

        return $attachment;
    }

    public function viewAttachment(User $actor, ExpenseAttachment $attachment): string
    {
        abort_unless($this->canViewExpenses($actor), 403);
        $this->assertBranchAccess($actor, (int) $attachment->branch_id);
        $this->audit->record('expense.attachment_viewed', $attachment, [], $attachment->tenant_id, [
            'module' => 'expenses', 'branch_id' => $attachment->branch_id,
        ]);

        return Storage::path($attachment->path);
    }

    public function approveExpense(User $actor, OperatingExpense $expense, bool $approve, ?string $reason = null): void
    {
        abort_unless($this->canApproveExpense($actor), 403);
        $this->assertBranchAccess($actor, (int) $expense->branch_id);
        abort_unless($expense->isPending(), 422);
        $this->makerChecker->assertCanApprove($actor, $expense->created_by);

        if (! $approve) {
            abort_unless(filled($reason), 422);
            $expense->update(['status' => 'rejected', 'rejection_reason' => $reason, 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record('expense.rejected', $expense, [], $expense->tenant_id, [
                'module' => 'expenses', 'branch_id' => $expense->branch_id, 'reason' => $reason,
            ]);

            return;
        }

        $this->limits->assertCanApprove($actor, 'expense', $expense->amount);
        $expense->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
        $this->audit->record('expense.approved', $expense, [], $expense->tenant_id, [
            'module' => 'expenses', 'branch_id' => $expense->branch_id,
        ]);
    }

    public function postExpense(User $actor, OperatingExpense $expense): void
    {
        abort_unless($this->canPostExpense($actor), 403);
        $this->assertBranchAccess($actor, (int) $expense->branch_id);
        abort_unless($expense->status === 'approved', 422);
        $this->assertPeriodOpen((int) $expense->tenant_id, (int) $expense->branch_id, optional($expense->expense_date)->toDateString() ?? now()->toDateString());

        $expense->update(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);

        if ($expense->kind === 'petty_cash_request' || $expense->category === 'petty_cash') {
            PettyCashEntry::query()->create([
                'tenant_id' => $expense->tenant_id,
                'branch_id' => $expense->branch_id,
                'kind' => 'expense',
                'entry_date' => $expense->expense_date ?? now()->toDateString(),
                'amount' => $expense->amount,
                'reference' => $expense->reference,
                'notes' => $expense->description,
                'operating_expense_id' => $expense->id,
                'created_by' => $actor->id,
            ]);
        }

        $this->audit->record('expense.posted', $expense, [], $expense->tenant_id, [
            'module' => 'expenses', 'branch_id' => $expense->branch_id,
        ]);
    }

    public function cancelExpense(User $actor, OperatingExpense $expense, string $reason): void
    {
        abort_unless($actor->isSuperAdmin() && $actor->hasPermission('expenses.execute'), 403);
        $this->assertBranchAccess($actor, (int) $expense->branch_id);
        abort_unless(in_array($expense->status, ['pending', 'approved', 'posted'], true), 422);
        abort_unless(strlen(trim($reason)) >= 3, 422);
        // Compensating cancellation — never hard-delete.
        $expense->update(['status' => 'cancelled', 'rejection_reason' => $reason]);
        $this->audit->record('expense.cancelled', $expense, [], $expense->tenant_id, [
            'module' => 'expenses', 'branch_id' => $expense->branch_id, 'reason' => $reason,
        ]);
    }

    public function createVendor(User $actor, Branch $branch, array $data): Vendor
    {
        abort_unless($this->canCreateVendor($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $vendor = Vendor::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'tax_no' => $data['tax_no'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('vendor.created', $vendor, [], $branch->tenant_id, [
            'module' => 'vendors', 'branch_id' => $branch->id,
        ]);

        return $vendor;
    }

    /**
     * @param  array{bill_date:string,due_date?:?string,reference:string,amount:float,description?:?string}  $data
     */
    public function createVendorBill(User $actor, Vendor $vendor, array $data): VendorBill
    {
        abort_unless($this->canRecordVendorBillPayment($actor), 403);
        $this->assertBranchAccess($actor, (int) $vendor->branch_id);
        $amount = round((float) $data['amount'], 2);
        abort_unless($amount > 0, 422);
        $this->assertPeriodOpen((int) $vendor->tenant_id, (int) $vendor->branch_id, $data['bill_date']);

        if (VendorBill::query()->where('tenant_id', $vendor->tenant_id)->where('reference', $data['reference'])->exists()) {
            throw ValidationException::withMessages(['reference' => 'Duplicate vendor bill reference.']);
        }

        $bill = VendorBill::query()->create([
            'tenant_id' => $vendor->tenant_id,
            'branch_id' => $vendor->branch_id,
            'vendor_id' => $vendor->id,
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'reference' => $data['reference'],
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => 'open',
            'description' => $data['description'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('vendor.bill_created', $bill, [], $vendor->tenant_id, [
            'module' => 'vendors', 'branch_id' => $vendor->branch_id,
        ]);

        return $bill;
    }

    /**
     * @param  array{amount:float,paid_on:string,method:string,reference?:?string,notes?:?string,vendor_bill_id?:?int}  $data
     */
    public function recordVendorPayment(User $actor, Vendor $vendor, array $data): VendorPayment
    {
        abort_unless($this->canRecordVendorBillPayment($actor), 403);
        $this->assertBranchAccess($actor, (int) $vendor->branch_id);
        $amount = round((float) $data['amount'], 2);
        abort_unless($amount > 0, 422);
        abort_unless(in_array($data['method'], ['cash', 'bank', 'cheque', 'wallet'], true), 422);
        $this->assertPeriodOpen((int) $vendor->tenant_id, (int) $vendor->branch_id, $data['paid_on']);

        if (! empty($data['reference'])) {
            $dup = VendorPayment::query()
                ->where('tenant_id', $vendor->tenant_id)
                ->where('reference', $data['reference'])
                ->exists();
            if ($dup) {
                throw ValidationException::withMessages(['reference' => 'Duplicate vendor payment reference.']);
            }
        }

        return DB::transaction(function () use ($actor, $vendor, $data, $amount) {
            $bill = null;
            if (! empty($data['vendor_bill_id'])) {
                $bill = VendorBill::query()->findOrFail($data['vendor_bill_id']);
                abort_unless((int) $bill->vendor_id === (int) $vendor->id, 422);
                abort_unless($amount <= $bill->outstanding() + 0.009, 422);
            }

            $payment = VendorPayment::query()->create([
                'tenant_id' => $vendor->tenant_id,
                'branch_id' => $vendor->branch_id,
                'vendor_id' => $vendor->id,
                'vendor_bill_id' => $bill?->id,
                'amount' => $amount,
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($bill) {
                $paid = (float) $bill->paid_amount + $amount;
                $bill->update([
                    'paid_amount' => $paid,
                    'status' => $paid + 0.009 >= (float) $bill->amount ? 'paid' : 'partial',
                ]);
            }

            $this->audit->record('vendor.payment_recorded', $payment, [], $vendor->tenant_id, [
                'module' => 'vendors', 'branch_id' => $vendor->branch_id,
            ]);

            return $payment;
        });
    }

    /**
     * @return array{opening: float, entries: \Illuminate\Support\Collection, closing: float}
     */
    public function pettyCashBook(User $actor, Branch $branch): array
    {
        abort_unless($this->canViewPettyCash($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $entries = PettyCashEntry::query()
            ->where('branch_id', $branch->id)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $balance = 0.0;
        foreach ($entries as $entry) {
            if (in_array($entry->kind, ['receipt', 'replenishment', 'opening'], true)) {
                $balance += (float) $entry->amount;
            } else {
                $balance -= (float) $entry->amount;
            }
        }

        return ['opening' => 0.0, 'entries' => $entries, 'closing' => round($balance, 2)];
    }

    public function postPettyCashEntry(User $actor, Branch $branch, array $data): PettyCashEntry
    {
        abort_unless($this->canPostPettyCash($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        abort_unless(in_array($data['kind'], ['opening', 'receipt', 'replenishment', 'expense'], true), 422);
        $this->assertPeriodOpen((int) $branch->tenant_id, (int) $branch->id, $data['entry_date']);

        $entry = PettyCashEntry::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'kind' => $data['kind'],
            'entry_date' => $data['entry_date'],
            'amount' => round((float) $data['amount'], 2),
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('petty_cash.entry', $entry, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id,
        ]);

        return $entry;
    }

    public function addBankStatementLine(User $actor, Branch $branch, array $data): BankStatementLine
    {
        abort_unless($this->canExecuteAccounts($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $line = BankStatementLine::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'statement_date' => $data['statement_date'],
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], 2),
            'reference' => $data['reference'] ?? null,
            'match_status' => 'unmatched',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('bank.statement_line_added', $line, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id,
        ]);

        return $line;
    }

    public function matchBankLine(User $actor, BankStatementLine $line, string $type, int $id): void
    {
        abort_unless($this->canExecuteAccounts($actor), 403);
        $this->assertBranchAccess($actor, (int) $line->branch_id);
        abort_unless(in_array($type, ['vendor_payment', 'storage_payment', 'operating_expense'], true), 422);

        $exists = match ($type) {
            'vendor_payment' => VendorPayment::query()->whereKey($id)->where('branch_id', $line->branch_id)->exists(),
            'storage_payment' => StoragePayment::query()->whereKey($id)->where('branch_id', $line->branch_id)->exists(),
            'operating_expense' => OperatingExpense::query()->whereKey($id)->where('branch_id', $line->branch_id)->where('status', 'posted')->exists(),
        };
        abort_unless($exists, 422);

        $amount = match ($type) {
            'vendor_payment' => (float) VendorPayment::query()->whereKey($id)->value('amount'),
            'storage_payment' => (float) StoragePayment::query()->whereKey($id)->value('amount'),
            'operating_expense' => (float) OperatingExpense::query()->whereKey($id)->value('amount'),
        };

        $status = abs($amount - (float) $line->amount) < 0.01 ? 'matched' : 'discrepant';
        $line->update([
            'match_status' => $status,
            'matched_type' => $type,
            'matched_id' => $id,
        ]);
        $this->audit->record('bank.line_'.$status, $line, [], $line->tenant_id, [
            'module' => 'accounts', 'branch_id' => $line->branch_id,
        ]);
    }

    public function completeReconciliation(User $actor, Branch $branch, string $start, string $end, ?string $notes): BankReconciliation
    {
        abort_unless($this->canExecuteAccounts($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $unmatched = BankStatementLine::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('statement_date', [$start, $end])
            ->where('match_status', 'unmatched')
            ->count();

        $recon = BankReconciliation::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'period_start' => $start,
            'period_end' => $end,
            'status' => $unmatched === 0 ? 'completed' : 'draft',
            'notes' => $notes ?? ($unmatched ? $unmatched.' unmatched line(s) remain.' : null),
            'created_by' => $actor->id,
            'completed_at' => $unmatched === 0 ? now() : null,
        ]);
        $this->audit->record('bank.reconciliation_'.$recon->status, $recon, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id,
        ]);

        return $recon;
    }

    /**
     * @return list<array{date:string,type:string,reference:string,debit:float,credit:float}>
     */
    public function dayBook(User $actor, Branch $branch, string $start, string $end): array
    {
        abort_unless($this->canViewDayBook($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $lines = [];
        StoragePayment::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereNull('reversed_at')
            ->whereBetween('paid_on', [$start, $end])
            ->each(function (StoragePayment $p) use (&$lines): void {
                if (! $p->isFundsEffective()) {
                    return;
                }
                $lines[] = [
                    'date' => optional($p->paid_on)->toDateString(),
                    'type' => 'customer_receipt',
                    'reference' => $p->reference ?? 'PAY-'.$p->id,
                    'debit' => 0.0,
                    'credit' => (float) $p->amount,
                ];
            });

        OperatingExpense::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$start, $end])
            ->each(function (OperatingExpense $e) use (&$lines): void {
                $lines[] = [
                    'date' => optional($e->expense_date)->toDateString(),
                    'type' => 'expense',
                    'reference' => $e->reference ?? 'EXP-'.$e->id,
                    'debit' => (float) $e->amount,
                    'credit' => 0.0,
                ];
            });

        VendorPayment::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereBetween('paid_on', [$start, $end])
            ->each(function (VendorPayment $p) use (&$lines): void {
                $lines[] = [
                    'date' => optional($p->paid_on)->toDateString(),
                    'type' => 'vendor_payment',
                    'reference' => $p->reference ?? 'VP-'.$p->id,
                    'debit' => (float) $p->amount,
                    'credit' => 0.0,
                ];
            });

        usort($lines, fn ($a, $b) => strcmp($a['date'].$a['reference'], $b['date'].$b['reference']));

        return $lines;
    }

    /**
     * @return array{revenue: float, expenses: float, profit: float, by_chamber: array, by_customer: array, notes: list<string>}
     */
    public function profitAndLoss(User $actor, Branch $branch, string $start, string $end): array
    {
        abort_unless($this->canViewProfitLoss($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        if ($actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
            abort_unless($actor->branches()->whereKey($branch->id)->exists(), 403);
        }

        $notes = [];
        $revenue = 0.0;
        $byCustomer = [];
        $byChamber = [];

        StorageInvoice::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', ['posted', 'locked'])
            ->whereBetween('invoice_date', [$start, $end])
            ->each(function (StorageInvoice $inv) use (&$revenue, &$byCustomer, &$byChamber): void {
                $net = $inv->netAmount();
                $revenue += $net;
                $cid = (int) $inv->customer_id;
                $byCustomer[$cid] = ($byCustomer[$cid] ?? 0) + $net;
                if ($inv->storage_lot_id) {
                    $chamberId = \App\Models\Csms\StorageLot::query()->whereKey($inv->storage_lot_id)->value('chamber_id');
                    if ($chamberId) {
                        $byChamber[(int) $chamberId] = ($byChamber[(int) $chamberId] ?? 0) + $net;
                    }
                }
            });

        $expenses = (float) OperatingExpense::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        $vendorSpend = (float) VendorPayment::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereBetween('paid_on', [$start, $end])
            ->sum('amount');

        // Avoid double-counting: vendor payments that settle expense-linked bills are cash outflows;
        // operating expenses already cover opex. Report vendor payments separately in notes if no COGS accounts.
        $notes[] = 'Revenue from posted invoices (net of credits/debits). Expenses from posted operating expenses only.';
        $notes[] = 'Vendor payments in period: '.number_format($vendorSpend, 2).' (AP cash outflows; not added to opex to avoid double-count).';
        if ($expenses === 0.0 && $revenue === 0.0) {
            $notes[] = 'No posted financial activity in this period for the selected branch.';
        }

        return [
            'revenue' => round($revenue, 2),
            'expenses' => round($expenses, 2),
            'profit' => round($revenue - $expenses, 2),
            'by_chamber' => $byChamber,
            'by_customer' => $byCustomer,
            'notes' => $notes,
        ];
    }

    public function exportAccounting(User $actor, Branch $branch, string $start, string $end): AccountingExport
    {
        abort_unless($this->canExecuteAccounts($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $configured = (bool) config('csms.accounting_export.enabled', false);
        $outcome = $configured
            ? 'Export accepted by configured integration.'
            : 'Accounting software export is not configured in this environment.';
        $status = $configured ? 'completed' : 'unavailable';

        $export = AccountingExport::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'period_start' => $start,
            'period_end' => $end,
            'format' => 'csv',
            'status' => $status,
            'outcome' => $outcome,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('accounts.export_'.$status, $export, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id, 'reason' => $outcome,
        ]);

        return $export;
    }

    public function requestPeriodClose(User $actor, Branch $branch, string $start, string $end): AccountingPeriod
    {
        abort_unless($this->canExecuteAccounts($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        $pendingExpenses = OperatingExpense::query()
            ->where('branch_id', $branch->id)
            ->where('status', 'pending')
            ->whereBetween('expense_date', [$start, $end])
            ->count();
        $unmatched = BankStatementLine::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('statement_date', [$start, $end])
            ->where('match_status', 'unmatched')
            ->count();
        if ($pendingExpenses > 0 || $unmatched > 0) {
            throw ValidationException::withMessages([
                'period' => "Cannot close: {$pendingExpenses} pending expense(s), {$unmatched} unmatched bank line(s).",
            ]);
        }

        $period = AccountingPeriod::query()->updateOrCreate(
            [
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'period_start' => $start,
                'period_end' => $end,
            ],
            [
                'status' => 'close_pending',
                'close_requested_by' => $actor->id,
                'close_approved_by' => null,
                'closed_at' => null,
            ]
        );
        $this->audit->record('accounts.period_close_requested', $period, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id,
        ]);

        return $period;
    }

    public function approvePeriodClose(User $actor, AccountingPeriod $period, bool $approve): void
    {
        abort_unless($this->canApproveAccounts($actor), 403);
        if ($period->branch_id) {
            $this->assertBranchAccess($actor, (int) $period->branch_id);
        }
        abort_unless($period->status === 'close_pending', 422);
        $this->makerChecker->assertCanApprove($actor, $period->close_requested_by);

        if (! $approve) {
            $period->update(['status' => 'open', 'close_requested_by' => null]);
            $this->audit->record('accounts.period_close_rejected', $period, [], $period->tenant_id, [
                'module' => 'accounts', 'branch_id' => $period->branch_id,
            ]);

            return;
        }

        $period->update([
            'status' => 'closed',
            'close_approved_by' => $actor->id,
            'closed_at' => now(),
        ]);
        $this->audit->record('accounts.period_closed', $period, [], $period->tenant_id, [
            'module' => 'accounts', 'branch_id' => $period->branch_id,
        ]);
    }

    public function reopenPeriod(User $actor, AccountingPeriod $period, string $reason): void
    {
        abort_unless($this->canApproveAccounts($actor), 403);
        abort_unless($period->isClosed(), 422);
        abort_unless(strlen(trim($reason)) >= 3, 422);

        $period->update([
            'status' => 'open',
            'reopen_reason' => $reason,
            'reopened_by' => $actor->id,
            'reopened_at' => now(),
        ]);
        $this->audit->record('accounts.period_reopened', $period, [], $period->tenant_id, [
            'module' => 'accounts', 'branch_id' => $period->branch_id, 'reason' => $reason,
        ]);
    }

    public function recordPrint(User $actor, string $report, Branch $branch): void
    {
        abort_unless($this->canPrintAccounts($actor) || $this->canPrintDayBook($actor) || $this->canPrintProfitLoss($actor), 403);
        $this->audit->record('accounts.'.$report.'_printed', $branch, [], $branch->tenant_id, [
            'module' => 'accounts', 'branch_id' => $branch->id,
        ]);
    }
}
