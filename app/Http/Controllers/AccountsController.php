<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\AccountingPeriod;
use App\Models\Csms\BankStatementLine;
use App\Models\Csms\Chamber;
use App\Models\Csms\Customer;
use App\Models\Csms\ExpenseAttachment;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\Vendor;
use App\Services\Accounts\AccountsDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountsController extends Controller
{
    public function __construct(private readonly AccountsDesk $desk) {}

    public function expenses(Request $request): View
    {
        abort_unless($this->desk->canViewExpenses($request->user()), 403);
        $user = $request->user();

        return view('accounts.expenses', [
            'expenses' => OperatingExpense::query()->visibleTo($user)->with(['vendor', 'chamber'])->latest()->limit(80)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function createExpense(Request $request): View
    {
        abort_unless($this->desk->canCreateExpense($request->user()), 403);
        $user = $request->user();

        return view('accounts.expense-create', [
            'branches' => $this->branchesFor($user),
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'vendors' => Vendor::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
            'supervisorOnly' => $user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin(),
        ]);
    }

    public function storeExpense(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['required', 'date'],
            'chamber_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'in:cash,bank,cheque,wallet'],
            'reference' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'in:expense,petty_cash_request'],
        ]);
        $expense = $this->desk->createExpense($request->user(), $branch, $data);

        return redirect()->route('expenses.show', $expense)->with('status', 'Expense submitted.');
    }

    public function showExpense(OperatingExpense $operating_expense): View
    {
        $user = request()->user();
        abort_unless($this->desk->canViewExpenses($user), 403);
        $this->desk->assertBranchAccess($user, (int) $operating_expense->branch_id);
        $operating_expense->load(['attachments', 'vendor', 'chamber', 'customer', 'creator']);

        return view('accounts.expense-show', [
            'expense' => $operating_expense,
            'desk' => $this->desk,
        ]);
    }

    public function attach(Request $request, OperatingExpense $operating_expense): RedirectResponse
    {
        $request->validate(['receipt' => ['required', 'file', 'max:5120']]);
        $this->desk->attachReceipt($request->user(), $operating_expense, $request->file('receipt'));

        return back()->with('status', 'Receipt attached.');
    }

    public function downloadAttachment(ExpenseAttachment $expense_attachment): BinaryFileResponse
    {
        $path = $this->desk->viewAttachment(request()->user(), $expense_attachment);

        return Response::download($path, $expense_attachment->original_name);
    }

    public function decideExpense(Request $request, OperatingExpense $operating_expense): RedirectResponse
    {
        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->approveExpense($request->user(), $operating_expense, (bool) $data['approve'], $data['reason'] ?? null);

        return back()->with('status', $data['approve'] ? 'Expense approved.' : 'Expense rejected.');
    }

    public function postExpense(Request $request, OperatingExpense $operating_expense): RedirectResponse
    {
        $this->desk->postExpense($request->user(), $operating_expense);

        return back()->with('status', 'Expense posted.');
    }

    public function cancelExpense(Request $request, OperatingExpense $operating_expense): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->cancelExpense($request->user(), $operating_expense, $data['reason']);

        return back()->with('status', 'Expense cancelled.');
    }

    public function vendors(Request $request): View
    {
        abort_unless($this->desk->canViewVendors($request->user()), 403);
        $user = $request->user();

        return view('accounts.vendors', [
            'vendors' => Vendor::query()->visibleTo($user)->latest()->limit(80)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeVendor(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $vendor = $this->desk->createVendor($request->user(), $branch, $data);

        return redirect()->route('vendors.show', $vendor)->with('status', 'Vendor created.');
    }

    public function showVendor(Vendor $vendor): View
    {
        $user = request()->user();
        abort_unless($this->desk->canViewVendors($user), 403);
        $this->desk->assertBranchAccess($user, (int) $vendor->branch_id);
        $vendor->load(['bills', 'payments']);

        return view('accounts.vendor-show', [
            'vendor' => $vendor,
            'desk' => $this->desk,
        ]);
    }

    public function storeBill(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->createVendorBill($request->user(), $vendor, $data);

        return back()->with('status', 'Vendor bill recorded.');
    }

    public function storeVendorPayment(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'vendor_bill_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', 'in:cash,bank,cheque,wallet'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->recordVendorPayment($request->user(), $vendor, $data);

        return back()->with('status', 'Vendor payment recorded.');
    }

    public function pettyCash(Request $request): View
    {
        abort_unless($this->desk->canViewPettyCash($request->user()), 403);
        $user = $request->user();
        $branch = $this->branchesFor($user)->first();
        $book = $branch ? $this->desk->pettyCashBook($user, $branch) : ['opening' => 0, 'entries' => collect(), 'closing' => 0];

        return view('accounts.petty-cash', [
            'branches' => $this->branchesFor($user),
            'branch' => $branch,
            'book' => $book,
            'desk' => $this->desk,
        ]);
    }

    public function storePettyCash(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:opening,receipt,replenishment,expense'],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->desk->postPettyCashEntry($request->user(), $branch, $data);

        return back()->with('status', 'Petty cash entry recorded.');
    }

    public function bankRecon(Request $request): View
    {
        $user = $request->user();
        // Matrix: SA view; Accountant execute (view via execute). Manager not granted.
        abort_unless(
            ($user->isSuperAdmin() && $this->desk->canViewAccounts($user))
            || $this->desk->canExecuteAccounts($user),
            403
        );
        $branch = $this->branchesFor($user)->first();

        return view('accounts.bank-recon', [
            'branches' => $this->branchesFor($user),
            'branch' => $branch,
            'lines' => $branch
                ? BankStatementLine::query()->where('branch_id', $branch->id)->latest()->limit(80)->get()
                : collect(),
            'desk' => $this->desk,
        ]);
    }

    public function storeBankLine(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'statement_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->desk->addBankStatementLine($request->user(), $branch, $data);

        return back()->with('status', 'Bank statement line added.');
    }

    public function matchBankLine(Request $request, BankStatementLine $bank_statement_line): RedirectResponse
    {
        $data = $request->validate([
            'matched_type' => ['required', 'in:vendor_payment,storage_payment,operating_expense'],
            'matched_id' => ['required', 'integer'],
        ]);
        $this->desk->matchBankLine($request->user(), $bank_statement_line, $data['matched_type'], (int) $data['matched_id']);

        return back()->with('status', 'Bank line match updated.');
    }

    public function completeRecon(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $recon = $this->desk->completeReconciliation($request->user(), $branch, $data['period_start'], $data['period_end'], $data['notes'] ?? null);

        return back()->with('status', $recon->status === 'completed'
            ? 'Bank reconciliation completed.'
            : 'Reconciliation saved as draft — unmatched lines remain.');
    }

    public function dayBook(Request $request): View
    {
        abort_unless($this->desk->canViewDayBook($request->user()), 403);
        $user = $request->user();
        $branch = Branch::query()->find($request->integer('branch_id')) ?: $this->branchesFor($user)->first();
        $start = $request->input('period_start', now()->startOfMonth()->toDateString());
        $end = $request->input('period_end', now()->toDateString());
        $lines = $branch ? $this->desk->dayBook($user, $branch, $start, $end) : [];

        return view('accounts.day-book', [
            'branches' => $this->branchesFor($user),
            'branch' => $branch,
            'start' => $start,
            'end' => $end,
            'lines' => $lines,
            'desk' => $this->desk,
        ]);
    }

    public function printDayBook(Request $request, Branch $branch): View
    {
        abort_unless($this->desk->canPrintDayBook($request->user()), 403);
        $start = $request->input('period_start', now()->startOfMonth()->toDateString());
        $end = $request->input('period_end', now()->toDateString());
        $this->desk->recordPrint($request->user(), 'day_book', $branch);

        return view('accounts.day-book-print', [
            'branch' => $branch,
            'lines' => $this->desk->dayBook($request->user(), $branch, $start, $end),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function profitLoss(Request $request): View
    {
        abort_unless($this->desk->canViewProfitLoss($request->user()), 403);
        $user = $request->user();
        $branch = Branch::query()->find($request->integer('branch_id')) ?: $this->branchesFor($user)->first();
        $start = $request->input('period_start', now()->startOfMonth()->toDateString());
        $end = $request->input('period_end', now()->toDateString());
        $report = $branch ? $this->desk->profitAndLoss($user, $branch, $start, $end) : null;

        return view('accounts.profit-loss', [
            'branches' => $this->branchesFor($user),
            'branch' => $branch,
            'start' => $start,
            'end' => $end,
            'report' => $report,
            'desk' => $this->desk,
        ]);
    }

    public function printProfitLoss(Request $request, Branch $branch): View
    {
        abort_unless($this->desk->canPrintProfitLoss($request->user()), 403);
        $start = $request->input('period_start', now()->startOfMonth()->toDateString());
        $end = $request->input('period_end', now()->toDateString());
        $this->desk->recordPrint($request->user(), 'profit_loss', $branch);

        return view('accounts.profit-loss-print', [
            'branch' => $branch,
            'report' => $this->desk->profitAndLoss($request->user(), $branch, $start, $end),
            'start' => $start,
            'end' => $end,
        ]);
    }

    public function export(Request $request): View
    {
        abort_unless($this->desk->canExecuteAccounts($request->user()) || $request->user()->isSuperAdmin(), 403);
        $user = $request->user();

        return view('accounts.export', [
            'branches' => $this->branchesFor($user),
            'exports' => \App\Models\Csms\AccountingExport::query()->visibleTo($user)->latest()->limit(40)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function runExport(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);
        $export = $this->desk->exportAccounting($request->user(), $branch, $data['period_start'], $data['period_end']);

        return back()->with('status', $export->outcome);
    }

    public function periods(Request $request): View
    {
        abort_unless($this->desk->canViewAccounts($request->user()), 403);
        $user = $request->user();

        return view('accounts.periods', [
            'branches' => $this->branchesFor($user),
            'periods' => AccountingPeriod::query()->visibleTo($user)->latest()->limit(40)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function requestClose(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);
        $this->desk->requestPeriodClose($request->user(), $branch, $data['period_start'], $data['period_end']);

        return back()->with('status', 'Period close requested — Super Admin approval required.');
    }

    public function decideClose(Request $request, AccountingPeriod $accounting_period): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approvePeriodClose($request->user(), $accounting_period, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Period closed.' : 'Period close rejected.');
    }

    public function reopen(Request $request, AccountingPeriod $accounting_period): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->reopenPeriod($request->user(), $accounting_period, $data['reason']);

        return back()->with('status', 'Period reopened.');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
