<?php

namespace App\Services\Notifications;

use App\Models\Csms\Booking;
use App\Models\Csms\Chamber;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Services\Bookings\Reservation;
use App\Services\Receivables\ReceivablesDesk;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Domain → Module 19 alert bridges. Idempotent; safe without external gateways.
 */
class OperationalAlertHooks
{
    public function __construct(
        private readonly NotificationsDesk $notifications,
        private readonly ReceivablesDesk $receivables,
        private readonly TenantContext $tenant,
    ) {}

    public function nearFullRatio(): float
    {
        $ratio = (float) config('csms.occupancy_near_full_ratio', 0.90);

        return max(0.01, min(1.0, $ratio));
    }

    /**
     * @return array{ratio: ?float, used: float, capacity: float, mode: string}|null
     */
    public function chamberOccupancy(Chamber $chamber): ?array
    {
        return $this->tenant->bypass(function () use ($chamber): ?array {
            $capacity = $chamber->capacity !== null ? (float) $chamber->capacity : 0.0;
            if ($capacity > 0) {
                $stored = (float) StorageLot::query()
                    ->where('chamber_id', $chamber->id)
                    ->where('status', 'stored')
                    ->sum('quantity');
                $reserved = (float) Booking::query()
                    ->where('chamber_id', $chamber->id)
                    ->whereIn('status', Reservation::RESERVED)
                    ->sum('quantity');
                $used = max($stored, $reserved);

                return [
                    'ratio' => $used / $capacity,
                    'used' => $used,
                    'capacity' => $capacity,
                    'mode' => $stored >= $reserved ? 'stored' : 'reserved',
                ];
            }

            $totalSlots = (int) $chamber->slots()->count();
            if ($totalSlots <= 0) {
                return null;
            }
            $occupied = (int) $chamber->slots()->where('status', 'occupied')->count();

            return [
                'ratio' => $occupied / $totalSlots,
                'used' => (float) $occupied,
                'capacity' => (float) $totalSlots,
                'mode' => 'slots',
            ];
        });
    }

    public function checkChamberOccupancy(Chamber $chamber): void
    {
        $stats = $this->chamberOccupancy($chamber);
        if ($stats === null || $stats['ratio'] === null) {
            return;
        }
        if ($stats['ratio'] + 0.0001 < $this->nearFullRatio()) {
            return;
        }

        $pct = round($stats['ratio'] * 100, 1);
        $this->afterCommit(function () use ($chamber, $stats, $pct): void {
            $this->notifications->raiseOperationalAlert('chamber_occupancy', [
                'tenant_id' => (int) $chamber->tenant_id,
                'branch_id' => (int) $chamber->branch_id,
                'title' => 'Chamber near full',
                'body' => sprintf(
                    'Chamber %s is at %s%% occupancy (%s / %s %s).',
                    $chamber->name,
                    $pct,
                    rtrim(rtrim(number_format($stats['used'], 3, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($stats['capacity'], 3, '.', ''), '0'), '.'),
                    $stats['mode'] === 'slots' ? 'slots' : (string) ($chamber->capacity_unit ?: 'units')
                ),
                'severity' => 'warning',
                'reference_type' => Chamber::class,
                'reference_id' => $chamber->id,
                // One alert while the near-full condition continues for this chamber.
                'idempotency_key' => 'chamber-occupancy:'.$chamber->id.':near-full',
            ]);
        });
    }

    public function lowStockDispatch(StorageLot $lot, float $requestedQty, ?string $context = null): void
    {
        $available = $lot->availableQuantity();
        $this->afterCommit(function () use ($lot, $requestedQty, $available, $context): void {
            $this->notifications->raiseOperationalAlert('low_stock_dispatch', [
                'tenant_id' => (int) $lot->tenant_id,
                'branch_id' => (int) $lot->branch_id,
                'title' => 'Insufficient stock for dispatch',
                'body' => sprintf(
                    'Requested %.3f exceeds available %.3f for lot %s%s.',
                    $requestedQty,
                    $available,
                    $lot->lot_number ?? ('#'.$lot->id),
                    $context ? ' ('.$context.')' : ''
                ),
                'severity' => 'warning',
                'reference_type' => StorageLot::class,
                'reference_id' => $lot->id,
                'idempotency_key' => 'low-stock:lot:'.$lot->id.':req:'.round($requestedQty, 3),
            ]);
        });
    }

    public function pendingApproval(
        Model $subject,
        int $tenantId,
        ?int $branchId,
        string $approvalType,
        string $summary,
    ): void {
        $this->afterCommit(function () use ($subject, $tenantId, $branchId, $approvalType, $summary): void {
            $this->notifications->raiseOperationalAlert('pending_approval', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'title' => 'Pending approval: '.$approvalType,
                'body' => $summary,
                'severity' => 'info',
                'reference_type' => $subject::class,
                'reference_id' => (int) $subject->getKey(),
                'idempotency_key' => 'pending-approval:'.$subject::class.':'.$subject->getKey(),
            ]);
        });
    }

    public function overdueInvoice(StorageInvoice $invoice, float $outstanding): void
    {
        $this->afterCommit(function () use ($invoice, $outstanding): void {
            $this->notifications->raiseOperationalAlert('overdue_invoice', [
                'tenant_id' => (int) $invoice->tenant_id,
                'branch_id' => (int) $invoice->branch_id,
                'title' => 'Overdue invoice',
                'body' => sprintf(
                    'Invoice %s is past due (%s) with outstanding balance %.2f.',
                    $invoice->number ?? ('#'.$invoice->id),
                    optional($invoice->due_date)?->toDateString() ?? 'n/a',
                    $outstanding
                ),
                'severity' => 'warning',
                'reference_type' => StorageInvoice::class,
                'reference_id' => $invoice->id,
                'idempotency_key' => 'overdue-invoice:'.$invoice->id,
            ]);
        });
    }

    /**
     * Scan posted invoices past due with outstanding balance.
     *
     * @return int Number of invoice alerts attempted (idempotent skips still count as attempts)
     */
    public function scanOverdueInvoices(?int $tenantId = null): int
    {
        return $this->tenant->bypass(function () use ($tenantId): int {
            $query = StorageInvoice::query()
                ->whereIn('status', ['posted', 'locked'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $count = 0;
            $query->orderBy('id')->chunkById(100, function ($invoices) use (&$count): void {
                foreach ($invoices as $invoice) {
                    $outstanding = $this->receivables->invoiceOutstanding($invoice);
                    if ($outstanding <= 0.009) {
                        continue;
                    }
                    $this->overdueInvoice($invoice, $outstanding);
                    $count++;
                }
            });

            return $count;
        });
    }

    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }
        $callback();
    }
}
