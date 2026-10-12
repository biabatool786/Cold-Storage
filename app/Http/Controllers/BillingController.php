<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\BillingAdjustment;
use App\Models\Csms\BillingBulkRun;
use App\Models\Csms\BillingWaiverRequest;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\RateAgreement;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Services\Billing\BillingDesk;
use App\Services\Operations\GoodsFlow;
use App\Services\Receivables\ReceivablesDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingDesk $desk,
        private readonly GoodsFlow $flow,
        private readonly ReceivablesDesk $receivables,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();

        return view('billing.index', [
            'invoices' => StorageInvoice::query()->visibleTo($user)->with(['customer', 'lot'])->latest()->limit(80)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->desk->canCreateInvoice($request->user()), 403);
        $user = $request->user();

        return view('billing.create', [
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'lots' => StorageLot::query()->visibleTo($user)->whereIn('status', ['stored', 'closed'])->orderByDesc('id')->limit(100)->get(),
            'orders' => DeliveryOrder::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'delivery_order_id' => ['nullable', 'integer'],
            'storage_lot_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'kind' => ['nullable', 'in:proforma,tax'],
        ]);
        $kind = $data['kind'] ?? 'tax';
        $customer = Customer::query()->findOrFail($data['customer_id']);

        if (! empty($data['storage_lot_id'])) {
            $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
            $invoice = $this->desk->createFromLot($request->user(), $lot, $kind);
        } else {
            $order = isset($data['delivery_order_id'])
                ? DeliveryOrder::query()->findOrFail($data['delivery_order_id'])
                : null;
            abort_unless(isset($data['amount']), 422);
            $invoice = $this->desk->createFromDelivery(
                $request->user(),
                $branch,
                $customer,
                $order,
                (float) $data['amount'],
                $kind,
            );
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created.');
    }

    public function show(StorageInvoice $storage_invoice): View
    {
        $user = request()->user();
        abort_unless($this->desk->canView($user) || $this->desk->canPrint($user), 403);
        $this->desk->assertBranchAccess($user, (int) $storage_invoice->branch_id);
        $storage_invoice->load(['customer', 'lot', 'lines.lot', 'adjustments', 'payments', 'deliveryOrder']);

        return view('billing.show', [
            'invoice' => $storage_invoice,
            'desk' => $this->desk,
            'waivers' => BillingWaiverRequest::query()->where('storage_invoice_id', $storage_invoice->id)->latest()->get(),
        ]);
    }

    public function update(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);
        $this->desk->updateDraft($request->user(), $storage_invoice, $data);

        return back()->with('status', 'Draft invoice updated.');
    }

    public function post(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $this->desk->post($request->user(), $storage_invoice);

        return back()->with('status', 'Invoice posted.');
    }

    public function discount(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->desk->applyDiscount($request->user(), $storage_invoice, (float) $data['percent'], $data['reason']);

        return back()->with('status', 'Discount submitted.');
    }

    public function decideDiscount(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveDiscount($request->user(), $storage_invoice, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Discount approved.' : 'Discount rejected.');
    }

    public function manualCharge(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_percent' => ['nullable', 'numeric', 'min:0'],
            'charge_type' => ['nullable', 'string', 'max:50'],
        ]);
        $this->desk->addManualCharge($request->user(), $storage_invoice, $data);

        return back()->with('status', 'Manual charge added.');
    }

    public function requestCancel(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->desk->requestCancellation($request->user(), $storage_invoice, $data['reason']);

        return back()->with('status', 'Cancellation submitted.');
    }

    public function decideCancel(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveCancellation($request->user(), $storage_invoice, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Invoice cancelled.' : 'Cancellation rejected.');
    }

    public function adjustment(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->desk->createAdjustment($request->user(), $storage_invoice, $data['kind'], (float) $data['amount'], $data['reason']);

        return back()->with('status', 'Credit/debit note created.');
    }

    public function decideAdjustment(Request $request, BillingAdjustment $billing_adjustment): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveAdjustment($request->user(), $billing_adjustment, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Adjustment posted.' : 'Adjustment rejected.');
    }

    public function waiver(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->desk->requestWaiver($request->user(), $storage_invoice, (float) $data['amount'], $data['reason']);

        return back()->with('status', 'Waiver requested.');
    }

    public function decideWaiver(Request $request, BillingWaiverRequest $billing_waiver_request): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveWaiver($request->user(), $billing_waiver_request, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Waiver approved.' : 'Waiver rejected.');
    }

    public function print(Request $request, StorageInvoice $storage_invoice): View
    {
        $this->desk->recordPrint($request->user(), $storage_invoice);
        $storage_invoice->load(['customer', 'lines', 'branch']);

        return view('billing.print', ['invoice' => $storage_invoice]);
    }

    public function send(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $result = $this->desk->recordSendAttempt($request->user(), $storage_invoice);

        return back()->with('status', $result['message']);
    }

    public function storePayment(Request $request, StorageInvoice $storage_invoice): RedirectResponse
    {
        $this->desk->assertBranchAccess($request->user(), (int) $storage_invoice->branch_id);
        abort_unless($storage_invoice->isPosted(), 422);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'in:cash,bank,cheque,wallet'],
            'reference' => ['nullable', 'string', 'max:100'],
            'cheque_number' => ['nullable', 'string', 'max:100'],
        ]);
        $branch = Branch::query()->findOrFail($storage_invoice->branch_id);
        $payment = $this->receivables->recordPayment($request->user(), $branch, [
            'customer_id' => (int) $storage_invoice->customer_id,
            'storage_invoice_id' => (int) $storage_invoice->id,
            'amount' => (float) $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'paid_on' => now()->toDateString(),
            'reference' => $data['reference'] ?? null,
            'cheque_number' => $data['cheque_number'] ?? null,
            'kind' => 'invoice',
            'allocate' => true,
        ]);

        return redirect()->route('payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function tariffs(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()) || $this->desk->canManageTariffs($request->user()), 403);
        $user = $request->user();

        return view('billing.tariffs', [
            'rates' => RateAgreement::query()->visibleTo($user)->with(['customer', 'commodity'])->latest()->limit(80)->get(),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeTariff(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'commodity_id' => ['nullable', 'integer'],
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'charge_type' => ['nullable', 'string', 'max:50'],
            'free_days' => ['nullable', 'integer', 'min:0'],
            'grace_days' => ['nullable', 'integer', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0'],
            'change_reason' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', 'string', 'max:50'],
        ]);
        $this->desk->proposeTariff($request->user(), $branch, $data);

        return back()->with('status', 'Tariff / rate card saved.');
    }

    public function decideTariff(Request $request, RateAgreement $rate_agreement): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveTariff($request->user(), $rate_agreement, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Tariff approved.' : 'Tariff rejected.');
    }

    public function settings(Request $request): View
    {
        abort_unless($this->desk->canViewSettings($request->user()), 403);
        $branches = $this->branchesFor($request->user());
        $branch = $branches->first();

        return view('billing.settings', [
            'branches' => $branches,
            'settings' => $branch ? $this->desk->settingsFor($branch) : null,
            'desk' => $this->desk,
        ]);
    }

    public function updateSettings(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'default_tax_percent' => ['required', 'numeric', 'min:0'],
            'default_free_days' => ['required', 'integer', 'min:0'],
            'default_grace_days' => ['required', 'integer', 'min:0'],
            'demurrage_rate' => ['required', 'numeric', 'min:0'],
            'late_fee_percent' => ['required', 'numeric', 'min:0'],
        ]);
        $this->desk->updateSettings($request->user(), $branch, $data);

        return back()->with('status', 'Billing settings saved.');
    }

    public function accrued(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();
        $lots = StorageLot::query()->visibleTo($user)->whereIn('status', ['stored', 'closed'])->with('customer')->latest()->limit(40)->get();
        $rows = $lots->map(fn (StorageLot $lot) => $this->desk->accrueLot($user, $lot))->all();

        return view('billing.accrued', ['rows' => $rows, 'desk' => $this->desk]);
    }

    public function bulk(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()) || $this->desk->canRequestBulk($request->user()), 403);
        $user = $request->user();

        return view('billing.bulk', [
            'runs' => BillingBulkRun::query()->visibleTo($user)->latest()->limit(40)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeBulk(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);
        $this->desk->requestBulkRun(
            $request->user(),
            $branch,
            \Illuminate\Support\Carbon::parse($data['period_start']),
            \Illuminate\Support\Carbon::parse($data['period_end']),
        );

        return back()->with('status', 'Bulk invoice run submitted for approval.');
    }

    public function decideBulk(Request $request, BillingBulkRun $billing_bulk_run): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveBulkRun($request->user(), $billing_bulk_run, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Bulk run completed.' : 'Bulk run rejected.');
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(\App\Enums\RoleName::Admin)) {
            return Branch::query()->when($user->tenant_id, fn ($q) => $q->where('tenant_id', $user->tenant_id))->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
