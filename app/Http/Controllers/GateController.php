<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\Customer;
use App\Models\Csms\GateBlock;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GateVisitor;
use App\Models\User;
use App\Services\Gate\GateRegister;
use App\Services\Operations\GoodsFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GateController extends Controller
{
    public function __construct(private readonly GateRegister $gate, private readonly GoodsFlow $flow) {}

    public function index(Request $request): View
    {
        return view('gates.today', [
            'entries' => $this->gate->today($request->user())->latest()->limit(50)->get(),
            'gate' => $this->gate,
            'branches' => $this->branches($request->user()),
            'customers' => Customer::query()->visibleTo($request->user())->orderBy('name')->get(),
            'bookings' => Booking::query()->visibleTo($request->user())->latest()->limit(30)->get(),
        ]);
    }

    public function history(Request $request): View
    {
        return view('gates.history', [
            'entries' => $this->gate->history($request->user())->latest()->limit(50)->get(),
            'gate' => $this->gate,
        ]);
    }

    public function historyPrint(Request $request): View
    {
        $this->gate->printed($request->user(), 'gate.register_printed');

        return view('gates.history', [
            'entries' => $this->gate->history($request->user())->latest()->limit(100)->get(),
            'gate' => $this->gate,
            'print' => true,
        ]);
    }

    public function expected(Request $request): View
    {
        return view('gates.expected', ['bookings' => $this->gate->expected($request->user())]);
    }

    public function visitors(Request $request): View
    {
        abort_unless($request->user()->hasPermission('gate.view'), 403);

        return view('gates.visitors', [
            'visitors' => GateVisitor::query()->visibleTo($request->user())->latest()->limit(50)->get(),
            'gate' => $this->gate,
            'branches' => $this->branches($request->user()),
        ]);
    }

    public function blocked(Request $request): View
    {
        abort_unless($request->user()->hasPermission('gate.view'), 403);

        return view('gates.blocked', [
            'blocks' => GateBlock::query()->visibleTo($request->user())->latest()->limit(50)->get(),
            'gate' => $this->gate,
            'branches' => $this->branches($request->user()),
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_no' => ['required', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'in:inbound,outbound'],
            'customer_id' => ['nullable', 'integer'],
            'booking_id' => ['nullable', 'integer'],
            'seal_number' => ['nullable', 'string', 'max:64'],
            'vehicle_condition' => ['nullable', 'string', 'max:255'],
        ]);
        unset($data['tenant_id'], $data['branch_id']);
        $entry = $this->gate->enter($request->user(), $branch, $data);

        return redirect()->route('gates.show', $entry)->with('status', 'Vehicle entered.');
    }

    public function show(Request $request, GateEntry $gate_entry): View
    {
        abort_unless($request->user()->hasPermission('gate.view'), 403);
        $this->gate->guard($request->user(), $gate_entry->branch);

        return view('gates.show', [
            'entry' => $gate_entry,
            'gate' => $this->gate,
            'blocked' => $this->gate->isBlocked($gate_entry),
        ]);
    }

    public function update(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_no' => ['required', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'in:inbound,outbound'],
            'seal_number' => ['nullable', 'string', 'max:64'],
            'vehicle_condition' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->gate->update($request->user(), $gate_entry, $data, $data['reason'] ?? null);

        return back()->with('status', 'Gate entry saved.');
    }

    public function destroy(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->gate->remove($request->user(), $gate_entry, $data['reason'] ?? null);

        return redirect()->route('gates.index')->with('status', 'Gate entry removed.');
    }

    public function photo(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate(['photo' => ['required', 'image', 'max:4096']]);
        $this->gate->photo($request->user(), $gate_entry, $data['photo']);

        return back()->with('status', 'Photo stored.');
    }

    public function verify(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $this->gate->verify($request->user(), $gate_entry, $data['code']);

        return back()->with('status', 'Gate pass verified.');
    }

    public function override(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->gate->override($request->user(), $gate_entry, $data['reason'] ?? null);

        return back()->with('status', 'Override approved.');
    }

    public function exit(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $this->flow->closeGate($request->user(), $gate_entry);

        return back()->with('status', 'Vehicle released.');
    }

    public function token(Request $request, GateEntry $gate_entry): View
    {
        $this->gate->guard($request->user(), $gate_entry->branch);
        $this->gate->printed($request->user(), 'gate.token_printed', $gate_entry);

        return view('gates.token', ['entry' => $gate_entry]);
    }

    public function storeVisitor(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'vehicle_no' => ['nullable', 'string', 'max:50'],
        ]);
        unset($data['tenant_id'], $data['branch_id']);
        $this->gate->visitor($request->user(), $branch, $data);

        return back()->with('status', 'Visitor registered.');
    }

    public function storeBlock(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_no' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->gate->block($request->user(), $branch, $data);

        return back()->with('status', 'Block saved.');
    }

    private function branches(User $user)
    {
        $query = Branch::query()->orderBy('name');
        if (! $user->isSuperAdmin() && ! $user->hasRole(\App\Enums\RoleName::Admin)) {
            $query->whereIn('id', $user->branches()->pluck('branches.id'));
        }

        return $query->get();
    }
}
