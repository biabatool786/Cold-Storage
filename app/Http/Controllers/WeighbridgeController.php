<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\GateEntry;
use App\Models\Csms\WeighbridgeCalibration;
use App\Models\Csms\WeighbridgeTolerance;
use App\Models\Csms\Weighment;
use App\Models\Csms\WeighmentEditRequest;
use App\Models\User;
use App\Services\Operations\GoodsFlow;
use App\Services\Weighbridge\WeighbridgeDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeighbridgeController extends Controller
{
    public function __construct(private readonly WeighbridgeDesk $desk, private readonly GoodsFlow $flow) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('weighbridge.view'), 403);

        return view('weighbridge.index', [
            'tickets' => Weighment::query()->visibleTo($request->user())->with('gateEntry')->latest()->limit(50)->get(),
            'desk' => $this->desk,
            'branches' => $this->branches($request->user()),
            'entries' => GateEntry::query()->visibleTo($request->user())->where('status', 'open')->latest()->limit(30)->get(),
            'tolerances' => WeighbridgeTolerance::query()->get()->keyBy('branch_id'),
        ]);
    }

    public function alerts(Request $request): View
    {
        abort_unless($request->user()->hasPermission('weighbridge.view'), 403);

        return view('weighbridge.alerts', [
            'tickets' => Weighment::query()->visibleTo($request->user())->where('tolerance_status', 'alert')->latest()->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function calibration(Request $request): View
    {
        abort_unless($request->user()->hasPermission('weighbridge.view'), 403);

        return view('weighbridge.calibration', [
            'rows' => WeighbridgeCalibration::query()->visibleTo($request->user())->latest()->limit(50)->get(),
            'desk' => $this->desk,
            'branches' => $this->branches($request->user()),
        ]);
    }

    public function store(Request $request, GateEntry $gate_entry): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:gross,tare'],
            'weight' => ['required', 'numeric', 'min:0'],
            'manual' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $ticket = $this->desk->record(
            $request->user(),
            $gate_entry,
            $data['kind'],
            (float) $data['weight'],
            $request->boolean('manual'),
            $data['reason'] ?? null,
        );

        return redirect()->route('weighments.show', $ticket)->with('status', 'Weight recorded.');
    }

    public function show(Request $request, Weighment $weighment): View
    {
        abort_unless($request->user()->hasPermission('weighbridge.view'), 403);
        $this->desk->guard($request->user(), $weighment->branch);
        $weighment->load(['gateEntry', 'editRequests']);

        return view('weighbridge.show', ['ticket' => $weighment, 'desk' => $this->desk]);
    }

    public function approve(Request $request, Weighment $weighment): RedirectResponse
    {
        $this->desk->approveManual($request->user(), $weighment);

        return back()->with('status', 'Manual weighment approved.');
    }

    public function update(Request $request, Weighment $weighment): RedirectResponse
    {
        $data = $request->validate([
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'tare_weight' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->edit($request->user(), $weighment, $data, $data['reason'] ?? null);

        return back()->with('status', 'Weighment change saved.');
    }

    public function decideEdit(Request $request, WeighmentEditRequest $weighment_edit_request): RedirectResponse
    {
        $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->decideEdit($request->user(), $weighment_edit_request, $request->boolean('approve'));

        return back()->with('status', 'Edit decision saved.');
    }

    public function destroy(Request $request, Weighment $weighment): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->desk->remove($request->user(), $weighment, $data['reason'] ?? null);

        return redirect()->route('weighments.index')->with('status', 'Weighment removed.');
    }

    public function tolerance(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['limit_kg' => ['required', 'numeric', 'min:0']]);
        $this->desk->configureTolerance($request->user(), $branch, (float) $data['limit_kg']);

        return back()->with('status', 'Tolerance saved.');
    }

    public function acknowledge(Request $request, Weighment $weighment): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        $this->desk->acknowledge($request->user(), $weighment, $data['comment'] ?? null);

        return back()->with('status', 'Tolerance alert acknowledged.');
    }

    public function storeCalibration(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'weighbridge_name' => ['nullable', 'string', 'max:100'],
            'calibrated_on' => ['required', 'date'],
            'next_due_on' => ['nullable', 'date'],
            'result' => ['nullable', 'in:pass,fail'],
            'certificate_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->desk->calibrate($request->user(), $branch, $data);

        return back()->with('status', 'Calibration recorded.');
    }

    public function print(Request $request, Weighment $weighment): View
    {
        $this->desk->printed($request->user(), $weighment);
        $weighment->load('gateEntry', 'branch');

        return view('weighbridge.print', ['ticket' => $weighment]);
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
