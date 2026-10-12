<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\AssetContract;
use App\Models\Csms\BreakdownTicket;
use App\Models\Csms\FacilityAsset;
use App\Models\Csms\MaintenanceWorkOrder;
use App\Models\Csms\PmSchedule;
use App\Models\Csms\SparePart;
use App\Models\Csms\Vendor;
use App\Models\User;
use App\Services\Maintenance\MaintenanceDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceDesk $desk) {}

    public function assets(Request $request): View
    {
        abort_unless($this->desk->canViewAssets($request->user()) || $this->desk->canManageAssets($request->user()), 403);
        $user = $request->user();

        return view('maintenance.assets', [
            'assets' => FacilityAsset::query()->visibleTo($user)->latest()->limit(100)->get(),
            'desk' => $this->desk,
            'costOnly' => $this->desk->seesAssetCostsOnly($user),
        ]);
    }

    public function createAsset(Request $request): View
    {
        abort_unless($this->desk->canManageAssets($request->user()), 403);

        return view('maintenance.asset-form', [
            'asset' => null,
            'branches' => $this->branchesFor($request->user()),
            'desk' => $this->desk,
        ]);
    }

    public function storeAsset(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'installed_at' => ['nullable', 'date'],
            'operational_status' => ['nullable', 'in:active,down,retired'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);
        $asset = $this->desk->createAsset($request->user(), $branch, $data);

        return redirect()->route('assets.show', $asset)->with('status', 'Asset created.');
    }

    public function showAsset(FacilityAsset $facility_asset): View
    {
        $user = request()->user();
        abort_unless($this->desk->canViewAssets($user) || $this->desk->canManageAssets($user), 403);
        $this->desk->assertBranchAccess($user, (int) $facility_asset->branch_id);
        $facility_asset->load(['contracts.vendor', 'tickets', 'workOrders']);

        return view('maintenance.asset-show', [
            'asset' => $facility_asset,
            'desk' => $this->desk,
            'costOnly' => $this->desk->seesAssetCostsOnly($user),
            'history' => $this->desk->canViewHistory($user) ? $this->desk->assetHistory($user, $facility_asset) : null,
        ]);
    }

    public function editAsset(FacilityAsset $facility_asset): View
    {
        abort_unless($this->desk->canManageAssets(request()->user()), 403);
        $this->desk->assertBranchAccess(request()->user(), (int) $facility_asset->branch_id);

        return view('maintenance.asset-form', [
            'asset' => $facility_asset,
            'branches' => $this->branchesFor(request()->user()),
            'desk' => $this->desk,
        ]);
    }

    public function updateAsset(Request $request, FacilityAsset $facility_asset): RedirectResponse
    {
        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'installed_at' => ['nullable', 'date'],
            'operational_status' => ['nullable', 'in:active,down,retired'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->desk->updateAsset($request->user(), $facility_asset, $data);

        return redirect()->route('assets.show', $facility_asset)->with('status', 'Asset updated.');
    }

    public function printQr(FacilityAsset $facility_asset): View
    {
        $this->desk->recordQrPrint(request()->user(), $facility_asset);

        return view('maintenance.asset-qr', ['asset' => $facility_asset]);
    }

    public function printHistory(FacilityAsset $facility_asset): View
    {
        $user = request()->user();
        $this->desk->recordHistoryPrint($user, $facility_asset);

        return view('maintenance.history-print', [
            'asset' => $facility_asset,
            'history' => $this->desk->assetHistory($user, $facility_asset),
        ]);
    }

    public function schedules(Request $request): View
    {
        abort_unless($this->desk->canViewAssets($request->user()) || $this->desk->canCreatePm($request->user()), 403);
        $user = $request->user();
        $schedules = PmSchedule::query()->visibleTo($user)->with('asset')->latest()->limit(80)->get();
        foreach ($schedules as $schedule) {
            $this->desk->refreshPmStatus($schedule);
        }
        $schedules = PmSchedule::query()->visibleTo($user)->with('asset')->latest()->limit(80)->get();

        return view('maintenance.schedules', [
            'schedules' => $schedules,
            'assets' => FacilityAsset::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'facility_asset_id' => ['required', 'integer'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'frequency_days' => ['required', 'integer', 'min:1'],
            'next_due_on' => ['required', 'date'],
            'assigned_vendor_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $asset = FacilityAsset::query()->findOrFail($data['facility_asset_id']);
        $this->desk->createPmSchedule($request->user(), $asset, $data);

        return back()->with('status', 'PM schedule created.');
    }

    public function completeSchedule(Request $request, PmSchedule $pm_schedule): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $this->desk->completePm($request->user(), $pm_schedule, $data['notes'] ?? null);

        return back()->with('status', 'Maintenance completed; next due date updated.');
    }

    public function tickets(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canReportBreakdown($user) || $this->desk->canViewAssets($user), 403);

        return view('maintenance.tickets', [
            'tickets' => BreakdownTicket::query()->visibleTo($user)->with(['asset', 'reporter'])->latest()->limit(80)->get(),
            'assets' => FacilityAsset::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function showTicket(BreakdownTicket $breakdown_ticket): View
    {
        $user = request()->user();
        abort_unless($this->desk->canReportBreakdown($user) || $this->desk->canViewAssets($user), 403);
        $this->desk->assertBranchAccess($user, (int) $breakdown_ticket->branch_id);
        $breakdown_ticket->load(['asset', 'workOrders', 'reporter']);

        return view('maintenance.ticket-show', [
            'ticket' => $breakdown_ticket,
            'vendors' => Vendor::query()->visibleTo($user)->orderBy('name')->limit(50)->get(),
            'users' => User::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'facility_asset_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
        ]);
        $asset = FacilityAsset::query()->findOrFail($data['facility_asset_id']);
        $ticket = $this->desk->raiseBreakdown($request->user(), $asset, $data);

        return redirect()->route('maintenance.tickets.show', $ticket)->with('status', 'Breakdown ticket '.$ticket->number.' opened.');
    }

    public function assignTicket(Request $request, BreakdownTicket $breakdown_ticket): RedirectResponse
    {
        $data = $request->validate([
            'assigned_vendor_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);
        $this->desk->assignTicket($request->user(), $breakdown_ticket, $data['assigned_vendor_id'] ?? null, $data['assigned_user_id'] ?? null);

        return back()->with('status', 'Technician/vendor assigned.');
    }

    public function closeTicket(Request $request, BreakdownTicket $breakdown_ticket): RedirectResponse
    {
        $data = $request->validate(['closure_notes' => ['required', 'string', 'max:1000']]);
        $this->desk->closeTicket($request->user(), $breakdown_ticket, $data['closure_notes']);

        return back()->with('status', 'Ticket closed.');
    }

    public function workOrders(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canCreateWorkOrder($user) || $this->desk->canViewAssets($user) || $this->desk->canUpdateWork($user), 403);

        return view('maintenance.work-orders', [
            'orders' => MaintenanceWorkOrder::query()->visibleTo($user)->with('asset')->latest()->limit(80)->get(),
            'assets' => FacilityAsset::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'tickets' => BreakdownTicket::query()->visibleTo($user)->where('status', '!=', 'closed')->latest()->limit(50)->get(),
            'schedules' => PmSchedule::query()->visibleTo($user)->latest()->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function storeWorkOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'facility_asset_id' => ['required', 'integer'],
            'breakdown_ticket_id' => ['nullable', 'integer'],
            'pm_schedule_id' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:2000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date'],
            'assigned_vendor_id' => ['nullable', 'integer'],
        ]);
        $asset = FacilityAsset::query()->findOrFail($data['facility_asset_id']);
        $order = $this->desk->createWorkOrder($request->user(), $asset, $data);

        return redirect()->route('maintenance.work-orders.show', $order)->with('status', 'Work order created.');
    }

    public function showWorkOrder(MaintenanceWorkOrder $maintenance_work_order): View
    {
        $user = request()->user();
        abort_unless($this->desk->canUpdateWork($user) || $this->desk->canCreateWorkOrder($user) || $this->desk->canViewAssets($user) || $this->desk->canApproveWorkOrderCost($user), 403);
        $this->desk->assertBranchAccess($user, (int) $maintenance_work_order->branch_id);
        $maintenance_work_order->load(['asset', 'logs', 'ticket', 'movements.part']);

        return view('maintenance.work-order-show', [
            'order' => $maintenance_work_order,
            'parts' => SparePart::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'vendors' => Vendor::query()->visibleTo($user)->orderBy('name')->limit(50)->get(),
            'users' => User::query()->where('tenant_id', $user->tenant_id)->orderBy('name')->limit(50)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function assignWorkOrder(Request $request, MaintenanceWorkOrder $maintenance_work_order): RedirectResponse
    {
        $data = $request->validate([
            'assigned_vendor_id' => ['nullable', 'integer'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);
        $this->desk->assignWorkOrder($request->user(), $maintenance_work_order, $data['assigned_vendor_id'] ?? null, $data['assigned_user_id'] ?? null);

        return back()->with('status', 'Technician/vendor assigned.');
    }

    public function decideCost(Request $request, MaintenanceWorkOrder $maintenance_work_order): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $this->desk->approveWorkOrderCost($request->user(), $maintenance_work_order, (bool) $data['approve']);

        return back()->with('status', $data['approve'] ? 'Cost approved.' : 'Cost rejected.');
    }

    public function updateWorkStatus(Request $request, MaintenanceWorkOrder $maintenance_work_order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,completed,cancelled'],
            'message' => ['required', 'string', 'max:1000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (isset($data['actual_cost'])) {
            $maintenance_work_order->actual_cost = $data['actual_cost'];
            $maintenance_work_order->save();
        }
        $this->desk->updateWorkStatus($request->user(), $maintenance_work_order, $data['status'], $data['message']);

        return back()->with('status', 'Work status updated.');
    }

    public function issuePart(Request $request, MaintenanceWorkOrder $maintenance_work_order): RedirectResponse
    {
        $data = $request->validate([
            'spare_part_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $part = SparePart::query()->findOrFail($data['spare_part_id']);
        $this->desk->issueStock($request->user(), $part, (float) $data['quantity'], $maintenance_work_order, $data['notes'] ?? null);

        return back()->with('status', 'Parts issued to work order.');
    }

    public function spares(Request $request): View
    {
        abort_unless($this->desk->canViewSpares($request->user()), 403);
        $user = $request->user();

        return view('maintenance.spares', [
            'parts' => SparePart::query()->visibleTo($user)->latest()->limit(80)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeSpare(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'part_code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->desk->createSparePart($request->user(), $branch, $data);

        return back()->with('status', 'Spare part created.');
    }

    public function moveSpare(Request $request, SparePart $spare_part): RedirectResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'in:receipt,issue'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['movement_type'] === 'receipt') {
            $this->desk->receiveStock($request->user(), $spare_part, (float) $data['quantity'], $data['notes'] ?? null);
        } else {
            $this->desk->issueStock($request->user(), $spare_part, (float) $data['quantity'], null, $data['notes'] ?? null);
        }

        return back()->with('status', 'Stock movement recorded.');
    }

    public function contracts(Request $request): View
    {
        abort_unless($this->desk->canViewContracts($request->user()), 403);
        $user = $request->user();

        return view('maintenance.contracts', [
            'contracts' => AssetContract::query()->visibleTo($user)->with(['asset', 'vendor'])->latest()->limit(80)->get(),
            'assets' => FacilityAsset::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'vendors' => Vendor::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'desk' => $this->desk,
            'showCost' => $user->isSuperAdmin()
                || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Accountant]),
        ]);
    }

    public function storeContract(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'facility_asset_id' => ['required', 'integer'],
            'kind' => ['required', 'in:warranty,amc'],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'coverage_notes' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);
        $asset = FacilityAsset::query()->findOrFail($data['facility_asset_id']);
        $this->desk->createContract($request->user(), $asset, $data);

        return back()->with('status', 'Contract recorded.');
    }

    public function history(Request $request): View
    {
        abort_unless($this->desk->canViewHistory($request->user()), 403);
        $user = $request->user();
        $assets = FacilityAsset::query()->visibleTo($user)->orderBy('name')->limit(100)->get();
        $rows = [];
        foreach ($assets as $asset) {
            $rows[] = [
                'asset' => $asset,
                'history' => $this->desk->assetHistory($user, $asset),
            ];
        }

        return view('maintenance.history', [
            'rows' => $rows,
            'desk' => $this->desk,
        ]);
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }
}
