<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\BadDebtWriteOff;
use App\Models\Csms\Customer;
use App\Models\Csms\CustomerDeposit;
use App\Models\Csms\DepositRefundRequest;
use App\Models\Csms\StockLoan;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Services\Receivables\ReceivablesDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivablesController extends Controller
{
    public function __construct(private readonly ReceivablesDesk $desk) {}

    public function index(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();

        return view('receivables.index', [
            'payments' => StoragePayment::query()->visibleTo($user)->with(['customer', 'invoice'])->latest()->limit(80)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->desk->canRecordPayment($request->user()), 403);
        $user = $request->user();

        return view('receivables.create', [
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'invoices' => StorageInvoice::query()->visibleTo($user)->whereIn('status', ['posted', 'locked'])->latest()->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'storage_invoice_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank,cheque,wallet'],
            'paid_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'cheque_number' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', 'in:invoice,advance,security'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocate' => ['nullable', 'boolean'],
        ]);
        $payment = $this->desk->recordPayment($request->user(), $branch, $data);

        return redirect()->route('payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function show(StoragePayment $storage_payment): View
    {
        $user = request()->user();
        abort_unless($this->desk->canView($user), 403);
        $this->desk->assertBranchAccess($user, (int) $storage_payment->branch_id);
        $storage_payment->load(['customer', 'invoice', 'allocations.invoice']);

        $openInvoices = [];
        if ($storage_payment->customer_id) {
            $openInvoices = StorageInvoice::query()
                ->where('customer_id', $storage_payment->customer_id)
                ->whereIn('status', ['posted', 'locked'])
                ->get()
                ->filter(fn (StorageInvoice $inv) => $this->desk->invoiceOutstanding($inv) > 0.009);
        }

        return view('receivables.show', [
            'payment' => $storage_payment,
            'desk' => $this->desk,
            'openInvoices' => $openInvoices,
        ]);
    }

    public function allocate(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $map = [];
        foreach ($data['allocations'] as $row) {
            $map[(int) $row['invoice_id']] = (float) $row['amount'];
        }
        $this->desk->allocate($request->user(), $storage_payment, $map);

        return back()->with('status', 'Payment allocated.');
    }

    public function editSameDay(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->desk->editSameDay($request->user(), $storage_payment, $data);

        return back()->with('status', 'Payment updated (same-day edit).');
    }

    public function cheque(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:deposited,cleared,bounced']]);
        $this->desk->updateChequeStatus($request->user(), $storage_payment, $data['status']);

        return back()->with('status', 'Cheque status updated to '.$data['status'].'.');
    }

    public function requestReverse(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->requestReverse($request->user(), $storage_payment, $data['reason']);

        return back()->with('status', 'Reversal requested.');
    }

    public function decideReverse(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveReverse($request->user(), $storage_payment, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Reversal approved.' : 'Reversal rejected.');
    }

    public function executeReverse(Request $request, StoragePayment $storage_payment): RedirectResponse
    {
        $this->desk->executeReverse($request->user(), $storage_payment);

        return back()->with('status', 'Payment reversed.');
    }

    public function receipt(Request $request, StoragePayment $storage_payment): View
    {
        $this->desk->recordPrint($request->user(), $storage_payment);
        $storage_payment->load(['customer', 'branch', 'allocations.invoice']);

        return view('receivables.receipt', ['payment' => $storage_payment, 'desk' => $this->desk]);
    }

    public function deposits(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();

        return view('receivables.deposits', [
            'deposits' => CustomerDeposit::query()->visibleTo($user)->with(['customer', 'refundRequests'])->latest()->limit(80)->get(),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'invoices' => StorageInvoice::query()->visibleTo($user)->whereIn('status', ['posted', 'locked'])->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function requestRefund(Request $request, CustomerDeposit $customer_deposit): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->desk->requestDepositRefund($request->user(), $customer_deposit, (float) $data['amount'], $data['reason']);

        return back()->with('status', 'Deposit refund requested.');
    }

    public function decideRefund(Request $request, DepositRefundRequest $deposit_refund_request): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveDepositRefund($request->user(), $deposit_refund_request, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Refund approved.' : 'Refund rejected.');
    }

    public function executeRefund(Request $request, DepositRefundRequest $deposit_refund_request): RedirectResponse
    {
        $this->desk->executeDepositRefund($request->user(), $deposit_refund_request);

        return back()->with('status', 'Deposit refund executed.');
    }

    public function useDeposit(Request $request, CustomerDeposit $customer_deposit): RedirectResponse
    {
        $data = $request->validate([
            'storage_invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $invoice = StorageInvoice::query()->findOrFail($data['storage_invoice_id']);
        $this->desk->useDeposit($request->user(), $customer_deposit, $invoice, (float) $data['amount']);

        return back()->with('status', 'Deposit applied to invoice.');
    }

    public function ledger(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();
        $customerId = $request->integer('customer_id') ?: null;
        $customer = $customerId ? Customer::query()->findOrFail($customerId) : null;
        $statement = $customer ? $this->desk->customerStatement($user, $customer) : null;

        return view('receivables.ledger', [
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'customer' => $customer,
            'statement' => $statement,
            'desk' => $this->desk,
        ]);
    }

    public function printLedger(Request $request, Customer $customer): View
    {
        $this->desk->recordStatementExport($request->user(), $customer);

        return view('receivables.ledger-print', [
            'customer' => $customer,
            'statement' => $this->desk->customerStatement($request->user(), $customer),
            'desk' => $this->desk,
        ]);
    }

    public function aging(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);

        return view('receivables.aging', [
            'rows' => $this->desk->receivableAging($request->user()),
            'desk' => $this->desk,
        ]);
    }

    public function printAging(Request $request): View
    {
        $this->desk->recordAgingExport($request->user());

        return view('receivables.aging-print', [
            'rows' => $this->desk->receivableAging($request->user()),
            'desk' => $this->desk,
        ]);
    }

    public function remind(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        $result = $this->desk->sendReminder($request->user(), $customer, $data['message'] ?? null);

        return back()->with('status', $result['message']);
    }

    public function hold(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->placeHold($request->user(), $customer, $data['reason']);

        return back()->with('status', 'Customer placed on dues hold.');
    }

    public function releaseHold(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->removeHold($request->user(), $customer, $data['reason']);

        return back()->with('status', 'Customer dues hold removed.');
    }

    public function writeOffs(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();

        return view('receivables.write-offs', [
            'rows' => BadDebtWriteOff::query()->visibleTo($user)->with(['customer', 'invoice'])->latest()->limit(80)->get(),
            'invoices' => StorageInvoice::query()->visibleTo($user)->whereIn('status', ['posted', 'locked'])->latest()->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function requestWriteOff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $invoice = StorageInvoice::query()->findOrFail($data['storage_invoice_id']);
        $this->desk->requestWriteOff($request->user(), $invoice, (float) $data['amount'], $data['reason']);

        return back()->with('status', 'Bad-debt write-off requested.');
    }

    public function decideWriteOff(Request $request, BadDebtWriteOff $bad_debt_write_off): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveWriteOff($request->user(), $bad_debt_write_off, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Write-off executed.' : 'Write-off rejected.');
    }

    public function loans(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();

        return view('receivables.loans', [
            'loans' => StockLoan::query()->visibleTo($user)->with(['customer', 'lot'])->latest()->limit(80)->get(),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'lots' => StorageLot::query()->visibleTo($user)->where('status', 'stored')->latest()->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeLoan(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'storage_lot_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $this->desk->createLoan($request->user(), $branch, $customer, $lot, (float) $data['amount'], $data['notes'] ?? null);

        return back()->with('status', 'Stock loan submitted for approval.');
    }

    public function decideLoan(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveLoan($request->user(), $stock_loan, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Loan approved.' : 'Loan rejected.');
    }

    public function repayLoan(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);
        $this->desk->recordLoanRepayment($request->user(), $stock_loan, (float) $data['amount']);

        return back()->with('status', 'Loan repayment recorded.');
    }

    public function requestLoanClose(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $this->desk->requestLoanClose($request->user(), $stock_loan);

        return back()->with('status', 'Loan close requested.');
    }

    public function decideLoanClose(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveLoanClose($request->user(), $stock_loan, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Loan close approved.' : 'Loan close rejected.');
    }

    public function executeLoanClose(Request $request, StockLoan $stock_loan): RedirectResponse
    {
        $this->desk->executeLoanClose($request->user(), $stock_loan);

        return back()->with('status', 'Loan closed.');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(\App\Enums\RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
