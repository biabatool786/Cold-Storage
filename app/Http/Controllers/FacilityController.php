<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\ChamberSetPointChange;
use App\Models\Csms\Level;
use App\Models\Csms\Rack;
use App\Models\Csms\Slot;
use App\Services\Facility\FacilityLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function __construct(private readonly FacilityLayout $layout) {}

    public function chambers(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('chambers.view'), 403);
        $user = $request->user();
        $search = trim($request->string('q')->toString());

        return view('facility.chambers', [
            'chambers' => Chamber::query()->with('branch')->visibleTo($user)
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
                ->withCount(['slots', 'slots as blocked_slots_count' => fn ($query) => $query->where('status', 'blocked')])
                ->orderBy('code')->get(),
            'layout' => $this->layout,
            'branches' => $this->branches($user),
            'search' => $search,
        ]);
    }

    public function storeChamber(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:20'],
        ]);
        $this->layout->createChamber($request->user(), $branch, $data);

        return back()->with('status', 'Chamber created.');
    }

    public function show(Chamber $chamber): View
    {
        abort_unless(request()->user()?->hasPermission('chambers.view'), 403);
        $this->layout->guard(request()->user(), $chamber->branch);
        $chamber->load(['racks.levels.slots', 'setPointChanges' => fn ($query) => $query->latest()]);

        return view('facility.show', ['chamber' => $chamber, 'layout' => $this->layout]);
    }

    public function update(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:20'],
        ]);
        $this->layout->updateChamber($request->user(), $chamber, $data);

        return back()->with('status', 'Chamber saved.');
    }

    public function destroy(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->layout->remove($request->user(), $chamber, $data['reason'] ?? null);

        return redirect()->route('chambers.index')->with('status', 'Chamber removed.');
    }

    public function proposeSetPoint(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate([
            'temperature' => ['nullable', 'numeric'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $this->layout->proposeSetPoint($request->user(), $chamber, isset($data['temperature']) ? (float) $data['temperature'] : null, isset($data['humidity']) ? (float) $data['humidity'] : null);

        return back()->with('status', 'Set-point submitted for approval.');
    }

    public function decideSetPoint(Request $request, ChamberSetPointChange $chamberSetPointChange): RedirectResponse
    {
        $this->layout->decideSetPoint($request->user(), $chamberSetPointChange, $request->boolean('approve'));

        return back()->with('status', $request->boolean('approve') ? 'Set-point approved.' : 'Set-point rejected.');
    }

    public function status(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,maintenance,defrost']]);
        $this->layout->changeStatus($request->user(), $chamber, $data['status']);

        return back()->with('status', 'Chamber status saved.');
    }

    public function storeRack(Request $request, Chamber $chamber): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->layout->createRack($request->user(), $chamber, $data);

        return back()->with('status', 'Rack created.');
    }

    public function updateRack(Request $request, Rack $rack): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'capacity' => ['nullable', 'numeric', 'min:0']]);
        $this->layout->updateRack($request->user(), $rack, $data);

        return back()->with('status', 'Rack saved.');
    }

    public function destroyRack(Request $request, Rack $rack): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->layout->remove($request->user(), $rack, $data['reason'] ?? null);

        return back()->with('status', 'Rack removed.');
    }

    public function storeLevel(Request $request, Rack $rack): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:50']]);
        $this->layout->createLevel($request->user(), $rack, $data);

        return back()->with('status', 'Level created.');
    }

    public function updateLevel(Request $request, Level $level): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $this->layout->updateLevel($request->user(), $level, $data);

        return back()->with('status', 'Level saved.');
    }

    public function destroyLevel(Request $request, Level $level): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->layout->remove($request->user(), $level, $data['reason'] ?? null);

        return back()->with('status', 'Level removed.');
    }

    public function storeSlot(Request $request, Level $level): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);
        $this->layout->createSlot($request->user(), $level, $data['code']);

        return back()->with('status', 'Slot created.');
    }

    public function updateSlot(Request $request, Slot $slot): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);
        $this->layout->updateSlot($request->user(), $slot, $data['code']);

        return back()->with('status', 'Slot saved.');
    }

    public function destroySlot(Request $request, Slot $slot): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->layout->remove($request->user(), $slot, $data['reason'] ?? null);

        return back()->with('status', 'Slot removed.');
    }

    public function block(Request $request, Slot $slot): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'blocked' => ['required', 'boolean']]);
        $this->layout->block($request->user(), $slot, $request->boolean('blocked'), $data['reason']);

        return back()->with('status', $request->boolean('blocked') ? 'Slot blocked.' : 'Slot available again.');
    }

    public function occupancy(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('chambers.view'), 403);
        $chambers = Chamber::query()->visibleTo($request->user())
            ->with(['branch', 'racks.levels.slots'])
            ->orderBy('code')->get();

        return view('facility.occupancy', ['chambers' => $chambers, 'layout' => $this->layout]);
    }

    private function branches($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(\App\Enums\RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
