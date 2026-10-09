<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
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
use App\Services\Inventory\InventoryDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryDesk $desk) {}

    public function index(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()), 403);
        $lots = StorageLot::query()->visibleTo($request->user())
            ->where('status', 'stored')
            ->with(['customer', 'commodity', 'chamber', 'slot', 'variety', 'grade'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('commodity_id'), fn ($q) => $q->where('commodity_id', $request->integer('commodity_id')))
            ->when($request->filled('chamber_id'), fn ($q) => $q->where('chamber_id', $request->integer('chamber_id')))
            ->latest('id')->limit(100)->get();

        return view('inventory.index', [
            'lots' => $lots,
            'desk' => $this->desk,
            'customers' => Customer::query()->visibleTo($request->user())->orderBy('name')->limit(100)->get(),
            'commodities' => Commodity::query()->orderBy('name')->limit(100)->get(),
            'chambers' => Chamber::query()->visibleTo($request->user())->orderBy('code')->get(),
            'filters' => $request->only(['customer_id', 'commodity_id', 'chamber_id']),
        ]);
    }

    public function ledger(Request $request): View
    {
        abort_unless($this->desk->canViewLedger($request->user()), 403);
        $entries = StockLedgerEntry::query()->visibleTo($request->user())
            ->with('lot')
            ->latest('id')->limit(100)->get();

        return view('inventory.ledger', [
            'entries' => $entries,
            'desk' => $this->desk,
            'printOnly' => ! $request->user()->hasPermission('inventory.view') && $request->user()->hasPermission('inventory.print'),
        ]);
    }

    public function show(Request $request, StorageLot $storage_lot): View
    {
        abort_unless($this->desk->canViewStock($request->user()), 403);
        $this->desk->guardLot($request->user(), $storage_lot);
        $storage_lot->load(['customer', 'commodity', 'chamber', 'slot.level.rack', 'receipt', 'ledgerEntries' => fn ($q) => $q->latest()->limit(30)]);

        return view('inventory.show', ['lot' => $storage_lot, 'desk' => $this->desk]);
    }

    public function suggestions(Request $request): View
    {
        $mode = $request->string('mode', 'fifo')->toString();
        $rows = $this->desk->suggestions(
            $request->user(),
            in_array($mode, ['fifo', 'fefo'], true) ? $mode : 'fifo',
            $request->filled('commodity_id') ? $request->integer('commodity_id') : null,
            $request->filled('customer_id') ? $request->integer('customer_id') : null,
        );

        return view('inventory.suggestions', [
            'rows' => $rows,
            'mode' => $mode,
            'desk' => $this->desk,
            'commodities' => Commodity::query()->orderBy('name')->limit(100)->get(),
            'customers' => Customer::query()->visibleTo($request->user())->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function transfers(Request $request): View
    {
        abort_unless($request->user()->hasPermission('transfers.view') || $this->desk->canCreateTransfer($request->user()), 403);

        return view('inventory.transfers', [
            'transfers' => StockTransfer::query()->visibleTo($request->user())->with(['lot', 'fromSlot', 'toSlot'])->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->whereNotNull('slot_id')->with('slot')->limit(50)->get(),
            'slots' => Slot::query()->visibleTo($request->user())->where('status', 'available')->orderBy('code')->limit(200)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'to_slot_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $slot = Slot::query()->findOrFail($data['to_slot_id']);
        $this->desk->requestTransfer($request->user(), $lot, $slot, (float) $data['quantity'], $data['reason'] ?? null);

        return back()->with('status', 'Transfer requested.');
    }

    public function approveTransfer(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        $this->desk->approveTransfer($request->user(), $stock_transfer);

        return back()->with('status', 'Transfer approved.');
    }

    public function executeTransfer(Request $request, StockTransfer $stock_transfer): RedirectResponse
    {
        $data = $request->validate([
            'from_slot_id' => ['required', 'integer'],
            'to_slot_id' => ['required', 'integer'],
        ]);
        $this->desk->executeTransfer(
            $request->user(),
            $stock_transfer,
            Slot::query()->findOrFail($data['from_slot_id']),
            Slot::query()->findOrFail($data['to_slot_id']),
        );

        return back()->with('status', 'Transfer executed.');
    }

    public function holds(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()) || $this->desk->canHold($request->user()), 403);

        return view('inventory.holds', [
            'holds' => StockHold::query()->visibleTo($request->user())->with('lot')->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeHold(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'kind' => ['required', 'in:hold,quarantine'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $this->desk->placeHold($request->user(), $lot, $data['kind'], (float) $data['quantity'], $data['reason']);

        return back()->with('status', 'Hold recorded.');
    }

    public function activateHold(Request $request, StockHold $stock_hold): RedirectResponse
    {
        $this->desk->activateHold($request->user(), $stock_hold);

        return back()->with('status', 'Hold activated.');
    }

    public function releaseHold(Request $request, StockHold $stock_hold): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->releaseHold($request->user(), $stock_hold, $data['reason']);

        return back()->with('status', 'Hold released.');
    }

    public function services(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()) || $this->desk->canCreateService($request->user()), 403);

        return view('inventory.services', [
            'services' => ValueAddedService::query()->visibleTo($request->user())->with('lot')->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'service_type' => ['required', 'in:sorting,grading,repacking'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $this->desk->createService(
            $request->user(),
            $lot,
            $data['service_type'],
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Service order created.');
    }

    public function approveService(Request $request, ValueAddedService $value_added_service): RedirectResponse
    {
        $this->desk->approveService($request->user(), $value_added_service);

        return back()->with('status', 'Service approved.');
    }

    public function shrinkage(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()) || $this->desk->canRecordShrinkage($request->user()), 403);

        return view('inventory.shrinkage', [
            'rows' => LotShrinkageRecord::query()->visibleTo($request->user())->with('lot')->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeShrinkage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:500'],
            'recorded_on' => ['required', 'date'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $this->desk->recordShrinkage($request->user(), $lot, (float) $data['quantity'], $data['reason'], $data['recorded_on']);

        return back()->with('status', 'Shrinkage recorded.');
    }

    public function counts(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()) || $this->desk->canCreateCycleCount($request->user()), 403);

        return view('inventory.counts', [
            'counts' => StockCount::query()->visibleTo($request->user())->with('chamber')->latest()->limit(50)->get(),
            'branches' => $this->branches($request->user()),
            'chambers' => Chamber::query()->visibleTo($request->user())->orderBy('code')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeCount(Request $request): RedirectResponse
    {
        $kind = $request->string('kind')->toString();
        if ($kind === 'physical') {
            abort_unless($this->desk->canCreatePhysicalAudit($request->user()), 403);
        } else {
            abort_unless($this->desk->canCreateCycleCount($request->user()), 403);
        }

        $data = $request->validate([
            'kind' => ['required', 'in:cycle,physical'],
            'chamber_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $chamber = ! empty($data['chamber_id']) ? Chamber::query()->findOrFail($data['chamber_id']) : null;
        $branch = $chamber?->branch ?? $this->branches($request->user())->first();
        abort_unless($branch !== null, 422);
        $count = $this->desk->createCount($request->user(), $branch, $data['kind'], $chamber, $data['notes'] ?? null);

        return redirect()->route('inventory.counts.show', $count)->with('status', 'Count task created.');
    }

    public function showCount(Request $request, StockCount $stock_count): View
    {
        abort_unless($this->desk->canViewStock($request->user()) || $this->desk->canCount($request->user()), 403);
        $this->desk->guard($request->user(), $stock_count->branch);
        $stock_count->load(['lines.lot', 'chamber']);

        return view('inventory.count-show', ['count' => $stock_count, 'desk' => $this->desk]);
    }

    public function recordCountLine(Request $request, StockCountLine $stock_count_line): RedirectResponse
    {
        $data = $request->validate(['counted_qty' => ['required', 'numeric', 'min:0']]);
        $this->desk->recordCountLine($request->user(), $stock_count_line, (float) $data['counted_qty']);

        return back()->with('status', 'Count saved.');
    }

    public function completeCount(Request $request, StockCount $stock_count): RedirectResponse
    {
        $this->desk->completeCount($request->user(), $stock_count);

        return back()->with('status', 'Count completed.');
    }

    public function variances(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()), 403);
        $lines = StockCountLine::query()
            ->whereIn('stock_count_id', StockCount::query()->visibleTo($request->user())->select('id'))
            ->whereNotNull('variance')
            ->where('variance', '!=', 0)
            ->with(['lot', 'count'])
            ->latest('id')->limit(100)->get();

        return view('inventory.variances', [
            'lines' => $lines,
            'adjustments' => StockAdjustment::query()->visibleTo($request->user())->with('lot')->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'kind' => ['required', 'in:adjustment,write_off'],
            'quantity_after' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $this->desk->requestAdjustment($request->user(), $lot, $data['kind'], (float) $data['quantity_after'], $data['reason']);

        return back()->with('status', 'Adjustment requested.');
    }

    public function approveAdjustment(Request $request, StockAdjustment $stock_adjustment): RedirectResponse
    {
        $this->desk->approveAdjustment($request->user(), $stock_adjustment);

        return back()->with('status', 'Adjustment approved.');
    }

    public function aging(Request $request): View
    {
        abort_unless($this->desk->canViewStock($request->user()), 403);
        $lots = StorageLot::query()->visibleTo($request->user())
            ->where('status', 'stored')
            ->with(['customer', 'commodity', 'chamber'])
            ->orderByRaw('expires_on is null')->orderBy('expires_on')
            ->limit(100)->get();

        return view('inventory.aging', [
            'lots' => $lots,
            'alerts' => $lots->filter(fn (StorageLot $lot) => $lot->expires_on && $lot->expires_on->lte(now()->addDays(14))),
            'desk' => $this->desk,
        ]);
    }

    public function labels(Request $request, StorageLot $storage_lot): View
    {
        abort_unless($this->desk->canPrintLabels($request->user()), 403);
        $this->desk->guardLot($request->user(), $storage_lot);
        app(\App\Services\Audit\AuditLogger::class)->record('inventory.label_printed', $storage_lot, [], $storage_lot->tenant_id, [
            'module' => 'inventory',
            'branch_id' => $storage_lot->branch_id,
        ]);

        return view('inventory.labels', ['lot' => $storage_lot]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse|View
    {
        abort_unless($this->desk->canExport($request->user()), 403);
        $user = $request->user();
        $chamberId = $request->integer('chamber_id') ?: null;

        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasRole(RoleName::Manager)) {
            abort_unless($chamberId !== null && $chamberId > 0, 422);
            $chamber = Chamber::query()->findOrFail($chamberId);
            $this->desk->guard($user, $chamber->branch);
        }

        $lots = StorageLot::query()->visibleTo($user)
            ->where('status', 'stored')
            ->when($chamberId, fn ($q) => $q->where('chamber_id', $chamberId))
            ->with(['customer', 'commodity', 'chamber', 'slot'])
            ->orderBy('id')->get();

        if ($request->boolean('download')) {
            app(\App\Services\Audit\AuditLogger::class)->record('inventory.export', $user, [
                'chamber_id' => $chamberId,
                'rows' => $lots->count(),
            ], $user->tenant_id, [
                'module' => 'inventory',
                'branch_id' => $chamberId ? Chamber::query()->find($chamberId)?->branch_id : null,
            ]);

            return response()->streamDownload(function () use ($lots) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Lot', 'Customer', 'Product', 'Chamber', 'Slot', 'Qty', 'Available', 'Held', 'Quarantine', 'Dispatched', 'Received', 'Expires']);
                foreach ($lots as $lot) {
                    fputcsv($out, [
                        $lot->lot_number, $lot->customer?->name, $lot->commodity?->name, $lot->chamber?->code,
                        $lot->slot?->code, $lot->quantity, $lot->availableQuantity(), $lot->held_qty,
                        $lot->quarantined_qty, $lot->dispatched_qty, $lot->received_at, $lot->expires_on,
                    ]);
                }
                fclose($out);
            }, 'stock-statement-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('inventory.export', [
            'lots' => $lots,
            'chambers' => Chamber::query()->visibleTo($user)->orderBy('code')->get(),
            'chamberId' => $chamberId,
            'desk' => $this->desk,
            'supervisor' => $user->hasRole(RoleName::Supervisor) && ! $user->hasRole(RoleName::Manager),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function branches($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
