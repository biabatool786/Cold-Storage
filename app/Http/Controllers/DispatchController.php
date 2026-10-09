<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\BranchTransferOut;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\DeliveryPickLine;
use App\Models\Csms\DispatchReturn;
use App\Models\Csms\GatePass;
use App\Models\Csms\Slot;
use App\Models\Csms\StorageLot;
use App\Services\Dispatch\DispatchDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function __construct(private readonly DispatchDesk $desk) {}

    public function index(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $user = $request->user();
        $orders = DeliveryOrder::query()->visibleTo($user)
            ->with(['customer', 'lot', 'pass'])
            ->when($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin(), fn ($q) => $q->whereIn('status', [
                'approved', 'picking', 'picked', 'loading', 'loaded', 'dispatched',
            ]))
            ->latest()->limit(50)->get();

        return view('dispatch.index', [
            'orders' => $orders,
            'desk' => $this->desk,
            'duesDetail' => $this->desk->seesDuesAmount($user),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->desk->canCreate($request->user()), 403);

        return view('dispatch.create', [
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->whereNotNull('slot_id')->with(['customer', 'commodity', 'slot'])->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'pick_mode' => ['nullable', 'in:fifo,fefo'],
            'submit' => ['nullable', 'boolean'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $order = $this->desk->open(
            $request->user(),
            $lot,
            (float) $data['quantity'],
            $data['pick_mode'] ?? 'fifo',
            $request->boolean('submit', true),
        );

        return redirect()->route('deliveries.show', $order)->with('status', 'Delivery order created.');
    }

    public function show(Request $request, DeliveryOrder $delivery_order): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $this->desk->guardOrder($request->user(), $delivery_order);
        $user = $request->user();
        if ($user->hasRole(RoleName::Gatekeeper) && ! $user->isSuperAdmin()) {
            abort_unless(in_array($delivery_order->status, [
                'approved', 'picking', 'picked', 'loading', 'loaded', 'dispatched',
            ], true), 404);
        }
        $delivery_order->load(['customer', 'lot.slot', 'commodity', 'pickLines.lot', 'pickLines.slot', 'pass', 'returns']);
        $checks = $delivery_order->lot
            ? $this->desk->runChecks($delivery_order->lot, (float) $delivery_order->quantity)
            : null;

        return view('dispatch.show', [
            'order' => $delivery_order,
            'desk' => $this->desk,
            'checks' => $checks,
            'duesDetail' => $this->desk->seesDuesAmount($user),
        ]);
    }

    public function edit(Request $request, DeliveryOrder $delivery_order): View
    {
        abort_unless(
            $this->desk->canEditDraft($request->user(), $delivery_order)
            || ($this->desk->canEditApproved($request->user()) && ! $delivery_order->isEditableDraft()),
            403
        );
        $this->desk->guardOrder($request->user(), $delivery_order);

        return view('dispatch.edit', ['order' => $delivery_order, 'desk' => $this->desk]);
    }

    public function update(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'pick_mode' => ['nullable', 'in:fifo,fefo'],
            'outgoing_temperature' => ['nullable', 'numeric'],
            'loader_notes' => ['nullable', 'string', 'max:500'],
            'submit' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if ($delivery_order->isEditableDraft()) {
            $data['submit'] = $request->boolean('submit');
            $this->desk->updateDraft($request->user(), $delivery_order, $data);
        } else {
            $this->desk->editApproved($request->user(), $delivery_order, $data, (string) ($data['reason'] ?? ''));
        }

        return redirect()->route('deliveries.show', $delivery_order)->with('status', 'Delivery order updated.');
    }

    public function recommend(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->desk->recommendDues($request->user(), $delivery_order, $data['note']);

        return back()->with('status', 'Dues override recommended.');
    }

    public function approve(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $this->desk->approve($request->user(), $delivery_order);

        return back()->with('status', 'Delivery order approved.');
    }

    public function reject(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->reject($request->user(), $delivery_order, $data['reason']);

        return redirect()->route('deliveries.index')->with('status', 'Delivery order rejected.');
    }

    public function cancel(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->cancel($request->user(), $delivery_order, $data['reason']);

        return redirect()->route('deliveries.index')->with('status', 'Delivery order cancelled.');
    }

    public function pickList(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate([
            'override' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->generatePickList($request->user(), $delivery_order, $request->boolean('override'), $data['reason'] ?? null);

        return back()->with('status', 'Pick list generated.');
    }

    public function pick(Request $request, DeliveryPickLine $delivery_pick_line): RedirectResponse
    {
        $data = $request->validate([
            'picked_qty' => ['required', 'numeric', 'min:0.001'],
            'slot_id' => ['nullable', 'integer'],
        ]);
        $slot = isset($data['slot_id']) ? Slot::query()->findOrFail($data['slot_id']) : null;
        $this->desk->pickLine($request->user(), $delivery_pick_line, (float) $data['picked_qty'], $slot);

        return back()->with('status', 'Pick recorded.');
    }

    public function temperature(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['temperature' => ['required', 'numeric']]);
        $this->desk->recordTemperature($request->user(), $delivery_order, (float) $data['temperature']);

        return back()->with('status', 'Outgoing temperature recorded.');
    }

    public function loaders(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate([
            'loader_count' => ['required', 'integer', 'min:0'],
            'loader_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->recordLoaders($request->user(), $delivery_order, (int) $data['loader_count'], $data['loader_notes'] ?? null);

        return back()->with('status', 'Loader details saved.');
    }

    public function verifyLoading(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['final_count' => ['required', 'numeric', 'min:0']]);
        $this->desk->verifyLoading($request->user(), $delivery_order, (float) $data['final_count']);

        return back()->with('status', 'Loading verified.');
    }

    public function issuePass(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $this->desk->issuePass($request->user(), $delivery_order);

        return back()->with('status', 'Gate pass issued.');
    }

    public function printPass(Request $request, GatePass $gate_pass): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $this->desk->guard($request->user(), $gate_pass->branch);
        $gate_pass->load(['deliveryOrder.customer', 'deliveryOrder.lot']);

        return view('dispatch.print-pass', ['pass' => $gate_pass, 'order' => $gate_pass->deliveryOrder]);
    }

    public function printNote(Request $request, DeliveryOrder $delivery_order): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $this->desk->guardOrder($request->user(), $delivery_order);
        $delivery_order->load(['customer', 'lot', 'pickLines.lot', 'pass']);

        return view('dispatch.print-note', ['order' => $delivery_order]);
    }

    public function verifyExit(Request $request, GatePass $gate_pass): RedirectResponse
    {
        $this->desk->verifyExit($request->user(), $gate_pass);

        return back()->with('status', 'Exit verified. Dispatch complete.');
    }

    public function returns(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()) || $this->desk->canHandleReturns($request->user()), 403);

        return view('dispatch.returns', [
            'returns' => DispatchReturn::query()->visibleTo($request->user())->with(['order', 'lot'])->latest()->limit(50)->get(),
            'orders' => DeliveryOrder::query()->visibleTo($request->user())->whereIn('status', ['dispatched', 'loaded'])->latest()->limit(30)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_order_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:500'],
            'condition' => ['nullable', 'string', 'max:255'],
        ]);
        $order = DeliveryOrder::query()->findOrFail($data['delivery_order_id']);
        $this->desk->createReturn($request->user(), $order, (float) $data['quantity'], $data['reason'], $data['condition'] ?? null);

        return back()->with('status', 'Return recorded.');
    }

    public function approveReturn(Request $request, DispatchReturn $dispatch_return): RedirectResponse
    {
        $this->desk->approveReturn($request->user(), $dispatch_return);

        return back()->with('status', 'Return approved.');
    }

    public function transferOuts(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()) || $this->desk->canTransferOut($request->user()), 403);

        return view('dispatch.transfer-outs', [
            'rows' => BranchTransferOut::query()->visibleTo($request->user())->with(['lot', 'toBranch'])->latest()->limit(50)->get(),
            'lots' => StorageLot::query()->visibleTo($request->user())->where('status', 'stored')->limit(50)->get(),
            'branches' => Branch::query()->where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeTransferOut(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'storage_lot_id' => ['required', 'integer'],
            'to_branch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $lot = StorageLot::query()->findOrFail($data['storage_lot_id']);
        $to = Branch::query()->findOrFail($data['to_branch_id']);
        $this->desk->requestBranchTransferOut($request->user(), $lot, $to, (float) $data['quantity'], $data['reason'] ?? null);

        return back()->with('status', 'Branch transfer-out requested.');
    }

    public function approveTransferOut(Request $request, BranchTransferOut $branch_transfer_out): RedirectResponse
    {
        $this->desk->approveBranchTransferOut($request->user(), $branch_transfer_out);

        return back()->with('status', 'Branch transfer-out approved.');
    }

    public function destroy(Request $request, DeliveryOrder $delivery_order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->delete($request->user(), $delivery_order, $data['reason']);

        return redirect()->route('deliveries.index')->with('status', 'Delivery order deleted.');
    }
}
