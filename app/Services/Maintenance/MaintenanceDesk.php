<?php

namespace App\Services\Maintenance;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\AssetContract;
use App\Models\Csms\BreakdownTicket;
use App\Models\Csms\FacilityAsset;
use App\Models\Csms\MaintenanceWorkOrder;
use App\Models\Csms\PmSchedule;
use App\Models\Csms\SparePart;
use App\Models\Csms\SparePartMovement;
use App\Models\Csms\Vendor;
use App\Models\Csms\WorkOrderLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
    ) {}

    public function canViewAssets(User $user): bool
    {
        return $user->hasPermission('maintenance.view')
            || ($user->hasRole(RoleName::Accountant) && $user->hasPermission('maintenance.view'));
    }

    public function canManageAssets(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && $user->hasPermission('maintenance.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function seesAssetCostsOnly(User $user): bool
    {
        return $user->hasRole(RoleName::Accountant) && ! $user->isSuperAdmin()
            && ! $user->hasAnyRole([RoleName::Admin, RoleName::Manager]);
    }

    public function canCreatePm(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canReportBreakdown(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor, RoleName::Gatekeeper,
            ]));
    }

    public function canAssign(User $user): bool
    {
        return $user->hasPermission('maintenance.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canCreateWorkOrder(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor]));
    }

    public function canApproveWorkOrderCost(User $user): bool
    {
        return $user->hasPermission('maintenance.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canUpdateWork(User $user): bool
    {
        return $user->hasPermission('maintenance.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
            ]));
    }

    public function canCloseTicket(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return $user->hasPermission('maintenance.execute');
        }

        return $user->hasRole(RoleName::Manager) && $user->hasPermission('maintenance.approve');
    }

    public function canManageSpares(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && $user->hasPermission('maintenance.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewSpares(User $user): bool
    {
        return $user->hasPermission('maintenance.view')
            || $this->canManageSpares($user);
    }

    public function canManageContracts(User $user): bool
    {
        return $user->hasPermission('maintenance.create')
            && $user->hasPermission('maintenance.edit')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canViewContracts(User $user): bool
    {
        return $user->hasPermission('maintenance.view')
            || ($user->hasRole(RoleName::Supervisor) && $user->hasPermission('maintenance.view'))
            || $this->canManageContracts($user);
    }

    public function canViewHistory(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function canPrintHistory(User $user): bool
    {
        return $user->hasPermission('maintenance.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canPrintQr(User $user): bool
    {
        return $user->hasPermission('maintenance.print')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function createAsset(User $actor, Branch $branch, array $data): FacilityAsset
    {
        abort_unless($this->canManageAssets($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $code = strtoupper(trim($data['asset_code']));
        if (FacilityAsset::query()->where('tenant_id', $branch->tenant_id)->where('asset_code', $code)->exists()) {
            throw ValidationException::withMessages(['asset_code' => 'Asset code already exists for this tenant.']);
        }

        $asset = FacilityAsset::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'asset_code' => $code,
            'name' => $data['name'],
            'title' => $data['name'],
            'category' => $data['category'] ?? null,
            'location' => $data['location'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'installed_at' => $data['installed_at'] ?? null,
            'operational_status' => $data['operational_status'] ?? 'active',
            'purchase_cost' => $data['purchase_cost'] ?? null,
            'chamber_id' => $data['chamber_id'] ?? null,
            'detail' => $data['detail'] ?? null,
            'status' => 'open',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('maintenance.asset_created', $asset, [], $branch->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $branch->id,
            'new' => ['asset_code' => $code, 'name' => $asset->name],
        ]);

        return $asset;
    }

    public function updateAsset(User $actor, FacilityAsset $asset, array $data): void
    {
        abort_unless($this->canManageAssets($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);
        if (! empty($data['asset_code']) && strtoupper($data['asset_code']) !== $asset->asset_code) {
            $code = strtoupper(trim($data['asset_code']));
            if (FacilityAsset::query()->where('tenant_id', $asset->tenant_id)->where('asset_code', $code)->whereKeyNot($asset->id)->exists()) {
                throw ValidationException::withMessages(['asset_code' => 'Asset code already exists for this tenant.']);
            }
            $data['asset_code'] = $code;
        }
        if (isset($data['name'])) {
            $data['title'] = $data['name'];
        }
        $old = $asset->only(array_keys($data));
        $asset->update($data);
        $this->audit->record('maintenance.asset_updated', $asset, $old, $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id, 'new' => $asset->only(array_keys($data)),
        ]);
    }

    public function createPmSchedule(User $actor, FacilityAsset $asset, array $data): PmSchedule
    {
        abort_unless($this->canCreatePm($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);

        $schedule = PmSchedule::query()->create([
            'tenant_id' => $asset->tenant_id,
            'branch_id' => $asset->branch_id,
            'facility_asset_id' => $asset->id,
            'maintenance_type' => $data['maintenance_type'],
            'frequency_days' => (int) ($data['frequency_days'] ?? 30),
            'next_due_on' => $data['next_due_on'],
            'assigned_vendor_id' => $data['assigned_vendor_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'status' => 'scheduled',
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->refreshPmStatus($schedule);
        $this->audit->record('maintenance.pm_created', $schedule, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id,
        ]);

        return $schedule->fresh();
    }

    public function completePm(User $actor, PmSchedule $schedule, ?string $notes = null): void
    {
        abort_unless($this->canUpdateWork($actor) || $this->canCreatePm($actor), 403);
        $this->assertBranchAccess($actor, (int) $schedule->branch_id);
        $schedule->update([
            'last_completed_on' => now()->toDateString(),
            'next_due_on' => now()->addDays((int) $schedule->frequency_days)->toDateString(),
            'status' => 'scheduled',
            'notes' => $notes ?? $schedule->notes,
        ]);
        $this->refreshPmStatus($schedule->fresh());
        $this->audit->record('maintenance.pm_completed', $schedule, [], $schedule->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $schedule->branch_id,
        ]);
    }

    public function refreshPmStatus(PmSchedule $schedule): void
    {
        $label = $schedule->dueLabel();
        $status = match ($label) {
            'overdue' => 'overdue',
            'due' => 'due',
            default => 'scheduled',
        };
        if ($schedule->status !== 'completed') {
            $schedule->update(['status' => $status]);
        }
    }

    public function raiseBreakdown(User $actor, FacilityAsset $asset, array $data): BreakdownTicket
    {
        abort_unless($this->canReportBreakdown($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);

        $ticket = BreakdownTicket::query()->create([
            'tenant_id' => $asset->tenant_id,
            'branch_id' => $asset->branch_id,
            'facility_asset_id' => $asset->id,
            'number' => 'BK-'.strtoupper(Str::random(6)),
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'reported_at' => now(),
            'status' => 'open',
            'reported_by' => $actor->id,
        ]);
        $asset->update(['operational_status' => 'down']);
        $this->audit->record('maintenance.breakdown_reported', $ticket, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id,
        ]);

        return $ticket;
    }

    public function assignTicket(User $actor, BreakdownTicket $ticket, ?int $vendorId, ?int $userId): void
    {
        abort_unless($this->canAssign($actor), 403);
        $this->assertBranchAccess($actor, (int) $ticket->branch_id);
        abort_unless(in_array($ticket->status, ['open', 'assigned'], true), 422);
        $ticket->update([
            'assigned_vendor_id' => $vendorId,
            'assigned_user_id' => $userId,
            'status' => 'assigned',
        ]);
        $this->audit->record('maintenance.ticket_assigned', $ticket, [], $ticket->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $ticket->branch_id,
        ]);
    }

    public function assignWorkOrder(User $actor, MaintenanceWorkOrder $order, ?int $vendorId, ?int $userId): void
    {
        abort_unless($this->canAssign($actor), 403);
        $this->assertBranchAccess($actor, (int) $order->branch_id);
        abort_unless(! in_array($order->status, ['completed', 'cancelled'], true), 422);
        $order->update([
            'assigned_vendor_id' => $vendorId,
            'assigned_user_id' => $userId,
        ]);
        $this->logWork($actor, $order, 'Technician/vendor assigned.', $order->status, $order->status);
        $this->audit->record('maintenance.work_order_assigned', $order, [], $order->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $order->branch_id,
        ]);
    }

    public function closeTicket(User $actor, BreakdownTicket $ticket, string $notes): void
    {
        abort_unless($this->canCloseTicket($actor), 403);
        $this->assertBranchAccess($actor, (int) $ticket->branch_id);
        abort_unless($ticket->status !== 'closed', 422);
        if ($actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
            $this->makerChecker->assertCanApprove($actor, $ticket->reported_by);
        }
        $ticket->update([
            'status' => 'closed',
            'closed_by' => $actor->id,
            'closed_at' => now(),
            'closure_notes' => $notes,
        ]);
        FacilityAsset::query()->whereKey($ticket->facility_asset_id)->update(['operational_status' => 'active']);
        $this->audit->record('maintenance.ticket_closed', $ticket, [], $ticket->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $ticket->branch_id, 'reason' => $notes,
        ]);
    }

    public function createWorkOrder(User $actor, FacilityAsset $asset, array $data): MaintenanceWorkOrder
    {
        abort_unless($this->canCreateWorkOrder($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);

        $isSupervisor = $actor->hasRole(RoleName::Supervisor) && ! $actor->isSuperAdmin()
            && ! $actor->hasAnyRole([RoleName::Admin, RoleName::Manager]);
        $kind = $isSupervisor ? 'request' : 'work_order';
        $status = $isSupervisor ? 'requested' : 'open';
        $estimated = round((float) ($data['estimated_cost'] ?? 0), 2);

        $order = MaintenanceWorkOrder::query()->create([
            'tenant_id' => $asset->tenant_id,
            'branch_id' => $asset->branch_id,
            'facility_asset_id' => $asset->id,
            'breakdown_ticket_id' => $data['breakdown_ticket_id'] ?? null,
            'pm_schedule_id' => $data['pm_schedule_id'] ?? null,
            'number' => 'WO-'.strtoupper(Str::random(6)),
            'kind' => $kind,
            'description' => $data['description'],
            'assigned_vendor_id' => $isSupervisor ? null : ($data['assigned_vendor_id'] ?? null),
            'assigned_user_id' => $isSupervisor ? null : ($data['assigned_user_id'] ?? null),
            'planned_start' => $data['planned_start'] ?? null,
            'planned_end' => $data['planned_end'] ?? null,
            'estimated_cost' => $estimated,
            'cost_status' => $estimated > 0 ? 'pending' : 'none',
            'cost_requested_by' => $estimated > 0 ? $actor->id : null,
            'status' => $status,
            'created_by' => $actor->id,
        ]);
        $this->logWork($actor, $order, 'Work order created.', null, $status);
        $this->audit->record('maintenance.work_order_created', $order, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id, 'new' => ['kind' => $kind],
        ]);

        return $order;
    }

    public function approveWorkOrderCost(User $actor, MaintenanceWorkOrder $order, bool $approve): void
    {
        abort_unless($this->canApproveWorkOrderCost($actor), 403);
        $this->assertBranchAccess($actor, (int) $order->branch_id);
        abort_unless($order->cost_status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $order->cost_requested_by ?? $order->created_by);
        $this->limits->assertCanApprove($actor, 'work_order_cost', $order->estimated_cost);

        if (! $approve) {
            $order->update(['cost_status' => 'rejected']);
            $this->audit->record('maintenance.work_order_cost_rejected', $order, [], $order->tenant_id, [
                'module' => 'maintenance', 'branch_id' => $order->branch_id,
            ]);

            return;
        }

        $order->update([
            'cost_status' => 'approved',
            'cost_approved_by' => $actor->id,
            'status' => $order->status === 'requested' ? 'open' : $order->status,
        ]);
        $this->audit->record('maintenance.work_order_cost_approved', $order, [], $order->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $order->branch_id,
        ]);
    }

    public function updateWorkStatus(User $actor, MaintenanceWorkOrder $order, string $status, string $message): void
    {
        abort_unless($this->canUpdateWork($actor), 403);
        $this->assertBranchAccess($actor, (int) $order->branch_id);
        abort_unless(in_array($status, ['open', 'in_progress', 'completed', 'cancelled'], true), 422);
        abort_unless(! in_array($order->status, ['completed', 'cancelled'], true), 422);
        $from = $order->status;
        $order->update([
            'status' => $status,
            'completion_notes' => $status === 'completed' ? $message : $order->completion_notes,
            'actual_cost' => $status === 'completed' ? ($order->actual_cost ?? $order->estimated_cost) : $order->actual_cost,
        ]);
        $this->logWork($actor, $order, $message, $from, $status);
        $this->audit->record('maintenance.work_order_status', $order, [], $order->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $order->branch_id,
            'old' => ['status' => $from], 'new' => ['status' => $status], 'reason' => $message,
        ]);
    }

    public function createSparePart(User $actor, Branch $branch, array $data): SparePart
    {
        abort_unless($this->canManageSpares($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);
        $code = strtoupper(trim($data['part_code']));
        if (SparePart::query()->where('tenant_id', $branch->tenant_id)->where('part_code', $code)->exists()) {
            throw ValidationException::withMessages(['part_code' => 'Part code already exists.']);
        }

        $part = SparePart::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'part_code' => $code,
            'name' => $data['name'],
            'unit' => $data['unit'] ?? 'pcs',
            'quantity_on_hand' => 0,
            'min_stock' => $data['min_stock'] ?? 0,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('maintenance.spare_created', $part, [], $branch->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $branch->id,
        ]);

        return $part;
    }

    public function receiveStock(User $actor, SparePart $part, float $qty, ?string $notes): void
    {
        abort_unless($this->canManageSpares($actor) || $this->canUpdateWork($actor), 403);
        $this->assertBranchAccess($actor, (int) $part->branch_id);
        abort_unless($qty > 0, 422);
        $this->moveStock($actor, $part, 'receipt', $qty, null, $notes);
    }

    public function issueStock(User $actor, SparePart $part, float $qty, ?MaintenanceWorkOrder $order, ?string $notes): void
    {
        abort_unless($this->canUpdateWork($actor) || $this->canManageSpares($actor), 403);
        $this->assertBranchAccess($actor, (int) $part->branch_id);
        abort_unless($qty > 0, 422);
        if ($qty > (float) $part->quantity_on_hand + 0.0001) {
            throw ValidationException::withMessages(['quantity' => 'Cannot issue more than available stock.']);
        }
        $this->moveStock($actor, $part, 'issue', $qty, $order, $notes);
    }

    private function moveStock(User $actor, SparePart $part, string $kind, float $qty, ?MaintenanceWorkOrder $order, ?string $notes): void
    {
        DB::transaction(function () use ($actor, $part, $kind, $qty, $order, $notes): void {
            $locked = SparePart::query()->lockForUpdate()->findOrFail($part->id);
            $balance = (float) $locked->quantity_on_hand;
            $balance = $kind === 'receipt' ? $balance + $qty : $balance - $qty;
            abort_unless($balance >= -0.0001, 422);
            $locked->update(['quantity_on_hand' => round($balance, 3)]);
            SparePartMovement::query()->create([
                'tenant_id' => $locked->tenant_id,
                'branch_id' => $locked->branch_id,
                'spare_part_id' => $locked->id,
                'kind' => $kind,
                'quantity' => $qty,
                'balance_after' => round($balance, 3),
                'maintenance_work_order_id' => $order?->id,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
            $this->audit->record('maintenance.spare_'.$kind, $locked, [], $locked->tenant_id, [
                'module' => 'maintenance', 'branch_id' => $locked->branch_id,
                'new' => ['quantity' => $qty, 'balance_after' => $balance],
            ]);
        });
    }

    public function createContract(User $actor, FacilityAsset $asset, array $data): AssetContract
    {
        abort_unless($this->canManageContracts($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);
        abort_unless(in_array($data['kind'], ['warranty', 'amc'], true), 422);

        $contract = AssetContract::query()->create([
            'tenant_id' => $asset->tenant_id,
            'branch_id' => $asset->branch_id,
            'facility_asset_id' => $asset->id,
            'kind' => $data['kind'],
            'provider_name' => $data['provider_name'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'coverage_notes' => $data['coverage_notes'] ?? null,
            'cost' => $data['cost'] ?? null,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('maintenance.contract_created', $contract, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id,
        ]);

        return $contract;
    }

    /**
     * @return array{downtime_hours: float, tickets: int, work_orders: int, actual_cost: float, parts_issues: int}
     */
    public function assetHistory(User $actor, FacilityAsset $asset): array
    {
        abort_unless($this->canViewHistory($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);

        $tickets = BreakdownTicket::query()->where('facility_asset_id', $asset->id)->get();
        $hours = 0.0;
        foreach ($tickets as $ticket) {
            $start = $ticket->reported_at ?? $ticket->created_at;
            $end = $ticket->closed_at ?? now();
            $hours += max(0, Carbon::parse($start)->diffInMinutes(Carbon::parse($end)) / 60);
        }
        $orders = MaintenanceWorkOrder::query()->where('facility_asset_id', $asset->id)->get();
        $actual = (float) $orders->where('status', 'completed')->sum(fn ($o) => (float) ($o->actual_cost ?? 0));
        $parts = SparePartMovement::query()
            ->where('kind', 'issue')
            ->whereIn('maintenance_work_order_id', $orders->pluck('id'))
            ->count();

        return [
            'downtime_hours' => round($hours, 2),
            'tickets' => $tickets->count(),
            'work_orders' => $orders->count(),
            'actual_cost' => round($actual, 2),
            'parts_issues' => $parts,
        ];
    }

    public function recordQrPrint(User $actor, FacilityAsset $asset): void
    {
        abort_unless($this->canPrintQr($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);
        $this->audit->record('maintenance.asset_qr_printed', $asset, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id,
        ]);
    }

    public function recordHistoryPrint(User $actor, FacilityAsset $asset): void
    {
        abort_unless($this->canPrintHistory($actor), 403);
        $this->assertBranchAccess($actor, (int) $asset->branch_id);
        $this->audit->record('maintenance.history_printed', $asset, [], $asset->tenant_id, [
            'module' => 'maintenance', 'branch_id' => $asset->branch_id,
        ]);
    }

    private function logWork(User $actor, MaintenanceWorkOrder $order, string $message, ?string $from, ?string $to): void
    {
        WorkOrderLog::query()->create([
            'tenant_id' => $order->tenant_id,
            'branch_id' => $order->branch_id,
            'maintenance_work_order_id' => $order->id,
            'message' => $message,
            'status_from' => $from,
            'status_to' => $to,
            'created_by' => $actor->id,
        ]);
    }
}
