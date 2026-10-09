<?php

namespace App\Http\Controllers;

use App\Models\Csms\Booking;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\PutAwayTask;
use App\Models\Csms\Slot;
use App\Models\Csms\StorageLot;
use App\Models\Csms\Weighment;
use App\Services\Receiving\ReceivingDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivingController extends Controller
{
    public function __construct(private readonly ReceivingDesk $desk) {}

    public function expected(Request $request): View
    {
        abort_unless($request->user()->hasPermission('receiving.view'), 403);

        $bookings = Booking::query()->visibleTo($request->user())
            ->whereIn('status', ['approved', 'confirmed', 'pending'])
            ->with(['customer', 'commodity'])
            ->latest()->limit(50)->get();

        $entries = GateEntry::query()->visibleTo($request->user())
            ->where('status', 'open')
            ->where('purpose', 'inbound')
            ->with(['weighments'])
            ->latest()->limit(50)->get();

        return view('receiving.expected', [
            'bookings' => $bookings,
            'entries' => $entries,
            'desk' => $this->desk,
        ]);
    }

    public function index(Request $request): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $receipts = GoodsReceipt::query()->visibleTo($request->user())
            ->with(['customer', 'commodity', 'gateEntry', 'lot'])
            ->latest()->limit(50)->get();

        return view('receiving.index', [
            'receipts' => $receipts,
            'desk' => $this->desk,
            'limited' => $this->desk->isGatekeeperLimited($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->desk->canCreate($request->user()), 403);
        $user = $request->user();

        return view('receiving.create', [
            'desk' => $this->desk,
            'entries' => GateEntry::query()->visibleTo($user)->where('status', 'open')->where('purpose', 'inbound')->latest()->limit(30)->get(),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'commodities' => Commodity::query()->orderBy('name')->limit(100)->get(),
            'bookings' => Booking::query()->visibleTo($user)->whereIn('status', ['approved', 'confirmed'])->latest()->limit(50)->get(),
            'weighments' => Weighment::query()->visibleTo($user)->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'commodity_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'booking_id' => ['nullable', 'integer'],
            'weighment_id' => ['nullable', 'integer'],
            'grade' => ['nullable', 'string', 'max:50'],
            'temperature' => ['nullable', 'numeric'],
            'condition_notes' => ['nullable', 'string', 'max:500'],
            'loader_count' => ['nullable', 'integer', 'min:0'],
            'unloading_notes' => ['nullable', 'string', 'max:500'],
            'shortage_qty' => ['nullable', 'numeric', 'min:0'],
            'damage_qty' => ['nullable', 'numeric', 'min:0'],
            'accepted_qty' => ['nullable', 'numeric', 'min:0'],
            'inspection_notes' => ['nullable', 'string', 'max:500'],
            'submit' => ['nullable', 'boolean'],
        ]);
        $data['submit'] = $request->boolean('submit');
        $receipt = $this->desk->createDraft($request->user(), $gate_entry, $data);

        return redirect()->route('grns.show', $receipt)->with('status', 'GRN draft saved.');
    }

    public function show(Request $request, GoodsReceipt $goods_receipt): View
    {
        abort_unless($this->desk->canView($request->user()), 403);
        $this->desk->guardReceipt($request->user(), $goods_receipt);
        $goods_receipt->load(['customer', 'commodity', 'gateEntry', 'booking', 'weighment', 'lot.putAwayTask', 'photos', 'chamber']);

        return view('receiving.show', [
            'receipt' => $goods_receipt,
            'desk' => $this->desk,
            'limited' => $this->desk->isGatekeeperLimited($request->user()),
            'chambers' => Chamber::query()->visibleTo($request->user())->where('branch_id', $goods_receipt->branch_id)->orderBy('code')->get(),
            'slots' => Slot::query()->visibleTo($request->user())->where('branch_id', $goods_receipt->branch_id)->where('status', 'available')->orderBy('code')->get(),
        ]);
    }

    public function edit(Request $request, GoodsReceipt $goods_receipt): View
    {
        $user = $request->user();
        abort_unless(
            $this->desk->canEditDraft($user, $goods_receipt)
            || ($goods_receipt->status === 'approved' && $this->desk->canEditApproved($user)),
            403
        );
        $this->desk->guardReceipt($user, $goods_receipt);

        return view('receiving.edit', [
            'receipt' => $goods_receipt->load(['gateEntry', 'weighment']),
            'desk' => $this->desk,
            'bookings' => Booking::query()->visibleTo($user)->where('branch_id', $goods_receipt->branch_id)->latest()->limit(50)->get(),
            'weighments' => Weighment::query()->where('gate_entry_id', $goods_receipt->gate_entry_id)->latest()->get(),
        ]);
    }

    public function update(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'grade' => ['nullable', 'string', 'max:50'],
            'temperature' => ['nullable', 'numeric'],
            'condition_notes' => ['nullable', 'string', 'max:500'],
            'loader_count' => ['nullable', 'integer', 'min:0'],
            'unloading_notes' => ['nullable', 'string', 'max:500'],
            'shortage_qty' => ['nullable', 'numeric', 'min:0'],
            'damage_qty' => ['nullable', 'numeric', 'min:0'],
            'accepted_qty' => ['nullable', 'numeric', 'min:0'],
            'inspection_notes' => ['nullable', 'string', 'max:500'],
            'booking_id' => ['nullable', 'integer'],
            'weighment_id' => ['nullable', 'integer'],
            'submit' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($goods_receipt->status === 'approved') {
            $this->desk->editApproved($request->user(), $goods_receipt, $data, (string) ($data['reason'] ?? ''));
        } else {
            $data['submit'] = $request->boolean('submit');
            $this->desk->updateDraft($request->user(), $goods_receipt, $data);
        }

        return redirect()->route('grns.show', $goods_receipt)->with('status', 'GRN updated.');
    }

    public function inspect(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate([
            'grade' => ['nullable', 'string', 'max:50'],
            'temperature' => ['nullable', 'numeric'],
            'condition_notes' => ['nullable', 'string', 'max:500'],
            'loader_count' => ['nullable', 'integer', 'min:0'],
            'unloading_notes' => ['nullable', 'string', 'max:500'],
            'shortage_qty' => ['nullable', 'numeric', 'min:0'],
            'damage_qty' => ['nullable', 'numeric', 'min:0'],
            'inspection_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->updateDraft($request->user(), $goods_receipt, $data);

        return back()->with('status', 'Inspection details saved.');
    }

    public function outcome(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:accept,partial,reject'],
            'accepted_qty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('confirm') || $this->desk->canConfirmOutcome($request->user())) {
            $this->desk->confirmOutcome(
                $request->user(),
                $goods_receipt,
                $data['outcome'],
                isset($data['accepted_qty']) ? (float) $data['accepted_qty'] : null,
                $data['notes'] ?? null,
            );
        } else {
            $this->desk->proposeOutcome(
                $request->user(),
                $goods_receipt,
                $data['outcome'],
                isset($data['accepted_qty']) ? (float) $data['accepted_qty'] : null,
                $data['notes'] ?? null,
            );
        }

        return back()->with('status', 'Outcome recorded.');
    }

    public function photo(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'kind' => ['required', 'in:damage,shortage,other'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);
        $this->desk->uploadPhoto($request->user(), $goods_receipt, $data['photo'], $data['kind'], $data['caption'] ?? null);

        return back()->with('status', 'Photo uploaded.');
    }

    public function approve(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate([
            'chamber_id' => ['required', 'integer'],
            'suggested_slot_id' => ['nullable', 'integer'],
        ]);
        $chamber = Chamber::query()->findOrFail($data['chamber_id']);
        $slot = isset($data['suggested_slot_id']) ? Slot::query()->findOrFail($data['suggested_slot_id']) : null;
        $this->desk->approve($request->user(), $goods_receipt, $chamber, $slot);

        return redirect()->route('grns.show', $goods_receipt)->with('status', 'GRN approved. Lot and put-away task created.');
    }

    public function cancel(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->cancel($request->user(), $goods_receipt, $data['reason']);

        return redirect()->route('grns.index')->with('status', 'GRN cancelled.');
    }

    public function destroy(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->desk->delete($request->user(), $goods_receipt, $data['reason']);

        return redirect()->route('grns.index')->with('status', 'GRN deleted.');
    }

    public function send(Request $request, GoodsReceipt $goods_receipt): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', 'in:email,sms,whatsapp']]);
        $this->desk->sendToCustomer($request->user(), $goods_receipt, $data['channel']);

        return back()->with('status', 'GRN send requested.');
    }

    public function print(Request $request, GoodsReceipt $goods_receipt): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $this->desk->guardReceipt($request->user(), $goods_receipt);
        $goods_receipt->load(['customer', 'commodity', 'gateEntry', 'weighment', 'lot', 'photos']);

        return view('receiving.print', ['receipt' => $goods_receipt]);
    }

    public function labels(Request $request, GoodsReceipt $goods_receipt): View
    {
        abort_unless($this->desk->canPrint($request->user()), 403);
        $this->desk->guardReceipt($request->user(), $goods_receipt);
        $goods_receipt->load('lot');

        return view('receiving.labels', ['receipt' => $goods_receipt, 'lot' => $goods_receipt->lot]);
    }

    public function putaways(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view') || $this->desk->canPutAway($request->user()), 403);

        return view('receiving.putaways', [
            'tasks' => PutAwayTask::query()->visibleTo($request->user())->with(['lot', 'suggestedSlot', 'slot', 'receipt'])->latest()->limit(50)->get(),
            'desk' => $this->desk,
            'slots' => Slot::query()->visibleTo($request->user())->where('status', 'available')->orderBy('code')->limit(200)->get(),
        ]);
    }

    public function executePutAway(Request $request, PutAwayTask $put_away_task): RedirectResponse
    {
        $data = $request->validate([
            'slot_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $slot = Slot::query()->findOrFail($data['slot_id']);
        $this->desk->executePutAway($request->user(), $put_away_task, $slot, $data['reason'] ?? null);

        return back()->with('status', 'Put-away completed.');
    }

    public function lots(Request $request): View
    {
        abort_unless($request->user()->hasPermission('inventory.view'), 403);

        return view('receiving.lots', [
            'lots' => StorageLot::query()->visibleTo($request->user())->with(['customer', 'chamber', 'slot', 'receipt'])->latest()->limit(50)->get(),
            'desk' => $this->desk,
            'limited' => false,
        ]);
    }

}
