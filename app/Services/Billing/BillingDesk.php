<?php

namespace App\Services\Billing;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\BillingAdjustment;
use App\Models\Csms\BillingBulkRun;
use App\Models\Csms\BillingSetting;
use App\Models\Csms\BillingWaiverRequest;
use App\Models\Csms\Customer;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\RateAgreement;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageInvoiceLine;
use App\Models\Csms\StorageLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ApprovalLimitEvaluator;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\FinancialDocumentGuard;
use App\Services\Authorization\MakerChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ApprovalLimitEvaluator $limits,
        private readonly FinancialDocumentGuard $financialDocuments,
    ) {}

    public function canView(User $user): bool
    {
        return $user->hasPermission('billing.view');
    }

    public function canPrint(User $user): bool
    {
        return $user->hasPermission('billing.print');
    }

    public function canSend(User $user): bool
    {
        return $user->hasPermission('billing.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canCreateInvoice(User $user): bool
    {
        return $user->hasPermission('billing.create')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canEditDraft(User $user): bool
    {
        return $user->hasPermission('billing.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canPost(User $user): bool
    {
        return $user->hasPermission('billing.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canManageTariffs(User $user): bool
    {
        return ($user->isSuperAdmin() && $user->hasPermission('billing.create'))
            || ($user->hasRole(RoleName::Manager) && $user->hasPermission('billing.create'))
            || ($user->hasRole(RoleName::Admin) && $user->hasPermission('billing.edit'));
    }

    public function canApproveTariff(User $user): bool
    {
        return $user->hasPermission('billing.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canConfigureSettings(User $user): bool
    {
        return $user->hasPermission('billing.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin));
    }

    public function canViewSettings(User $user): bool
    {
        return $this->canConfigureSettings($user)
            || ($user->hasPermission('billing.view') && $user->hasRole(RoleName::Accountant));
    }

    public function canRequestBulk(User $user): bool
    {
        return $user->hasPermission('billing.execute')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveBulk(User $user): bool
    {
        return $user->hasPermission('billing.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canRequestCancel(User $user): bool
    {
        return $user->hasPermission('billing.edit') && $user->hasRole(RoleName::Accountant);
    }

    public function canCancel(User $user): bool
    {
        return ($user->isSuperAdmin() && $user->hasPermission('billing.execute'))
            || ($user->hasRole(RoleName::Manager) && $user->hasPermission('billing.approve'));
    }

    public function canCreateAdjustment(User $user): bool
    {
        return ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant))
            && $user->hasPermission('billing.create');
    }

    public function canApproveAdjustment(User $user): bool
    {
        return $user->hasPermission('billing.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canApplyDiscount(User $user): bool
    {
        return $user->hasPermission('billing.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canApproveDiscount(User $user): bool
    {
        return $user->isSuperAdmin() && $user->hasPermission('billing.approve');
    }

    public function canAddManualCharge(User $user): bool
    {
        return $this->canCreateAdjustment($user) || (
            $user->hasRole(RoleName::Manager) && $user->hasPermission('billing.approve')
        );
    }

    public function canRequestWaiver(User $user): bool
    {
        return $user->hasRole(RoleName::Accountant) && $user->hasPermission('billing.edit');
    }

    public function canApproveWaiver(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasRole(RoleName::Manager) && $user->hasPermission('billing.approve'));
    }

    public function assertBranchAccess(User $actor, int $branchId): Branch
    {
        $branch = Branch::query()->find($branchId);
        abort_unless($branch && $this->branches->allows($actor, $branch), 403);

        return $branch;
    }

    public function settingsFor(Branch $branch): BillingSetting
    {
        return BillingSetting::query()->firstOrCreate(
            ['tenant_id' => $branch->tenant_id, 'branch_id' => $branch->id],
            [
                'default_tax_percent' => 0,
                'default_free_days' => 0,
                'default_grace_days' => 0,
                'demurrage_rate' => 0,
                'late_fee_percent' => 0,
            ],
        );
    }

    public function updateSettings(User $actor, Branch $branch, array $data): BillingSetting
    {
        abort_unless($this->canConfigureSettings($actor), 403);
        $this->assertBranchAccess($actor, (int) $branch->id);
        $settings = $this->settingsFor($branch);
        $old = $settings->only([
            'default_tax_percent', 'default_free_days', 'default_grace_days', 'demurrage_rate', 'late_fee_percent',
        ]);
        $settings->update([
            'default_tax_percent' => $data['default_tax_percent'] ?? 0,
            'default_free_days' => $data['default_free_days'] ?? 0,
            'default_grace_days' => $data['default_grace_days'] ?? 0,
            'demurrage_rate' => $data['demurrage_rate'] ?? 0,
            'late_fee_percent' => $data['late_fee_percent'] ?? 0,
            'updated_by' => $actor->id,
        ]);
        $this->audit->record('billing.settings_updated', $settings, [], $branch->tenant_id, [
            'module' => 'billing', 'branch_id' => $branch->id, 'old' => $old, 'new' => $settings->fresh()->only(array_keys($old)),
        ]);

        return $settings->fresh();
    }

    public function proposeTariff(User $actor, Branch $branch, array $data): RateAgreement
    {
        abort_unless($this->canManageTariffs($actor), 403);
        $this->assertBranchAccess($actor, (int) $branch->id);
        $customer = Customer::query()->visibleTo($actor)->findOrFail($data['customer_id']);
        abort_unless((int) $customer->branch_id === (int) $branch->id, 403);

        $needsApproval = ! $actor->isSuperAdmin();
        $rate = RateAgreement::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'commodity_id' => $data['commodity_id'] ?? null,
            'rate' => $data['rate'],
            'unit' => $data['unit'] ?? 'kg',
            'kind' => $data['kind'] ?? 'standard',
            'charge_type' => $data['charge_type'] ?? 'storage',
            'free_days' => $data['free_days'] ?? 0,
            'grace_days' => $data['grace_days'] ?? 0,
            'tax_percent' => $data['tax_percent'] ?? 0,
            'change_reason' => $data['change_reason'] ?? null,
            'approval_status' => $needsApproval ? 'pending' : 'approved',
            'status' => $needsApproval ? 'pending' : 'active',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'created_by' => $actor->id,
            'approved_by' => $needsApproval ? null : $actor->id,
        ]);

        $this->audit->record('billing.tariff_proposed', $rate, [], $branch->tenant_id, [
            'module' => 'billing', 'branch_id' => $branch->id,
            'new' => $rate->only(['rate', 'charge_type', 'free_days', 'grace_days', 'tax_percent', 'approval_status']),
        ]);

        return $rate;
    }

    public function approveTariff(User $actor, RateAgreement $rate, bool $approve = true): void
    {
        abort_unless($this->canApproveTariff($actor), 403);
        abort_unless($rate->approval_status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $rate->created_by);
        $this->assertBranchAccess($actor, (int) $rate->branch_id);

        $rate->update([
            'approval_status' => $approve ? 'approved' : 'rejected',
            'status' => $approve ? 'active' : 'rejected',
            'approved_by' => $actor->id,
        ]);
        $this->audit->record($approve ? 'billing.tariff_approved' : 'billing.tariff_rejected', $rate, [], $rate->tenant_id, [
            'module' => 'billing', 'branch_id' => $rate->branch_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function accrueLot(User $actor, StorageLot $lot, ?Carbon $asOf = null): array
    {
        abort_unless($this->canView($actor), 403);
        $this->assertBranchAccess($actor, (int) $lot->branch_id);
        $asOf ??= now();
        $rate = $this->rateForLot($lot);
        $settings = $this->settingsFor(Branch::query()->findOrFail($lot->branch_id));
        $received = $lot->received_at ? Carbon::parse($lot->received_at)->startOfDay() : null;
        $storageDays = $received ? $received->diffInDays($asOf->copy()->startOfDay()) + 1 : 0;
        $free = (int) ($rate?->free_days ?? $settings->default_free_days);
        $grace = (int) ($rate?->grace_days ?? $settings->default_grace_days);
        $billable = max(0, $storageDays - $free - $grace);
        $unitRate = (float) ($rate?->rate ?? 0);
        $qty = (float) $lot->quantity;
        $amount = round($qty * $unitRate * $billable, 2);
        $taxPercent = (float) ($rate?->tax_percent ?? $settings->default_tax_percent);
        $tax = round($amount * $taxPercent / 100, 2);

        return [
            'lot' => $lot,
            'rate' => $rate,
            'storage_days' => $storageDays,
            'free_days' => $free,
            'grace_days' => $grace,
            'billable_days' => $billable,
            'quantity' => $qty,
            'unit_rate' => $unitRate,
            'amount' => $amount,
            'tax_percent' => $taxPercent,
            'tax_amount' => $tax,
            'total' => round($amount + $tax, 2),
            'already_invoiced' => $this->lotAlreadyInvoiced($lot),
        ];
    }

    public function rateForLot(StorageLot $lot): ?RateAgreement
    {
        return RateAgreement::query()
            ->where('branch_id', $lot->branch_id)
            ->where('customer_id', $lot->customer_id)
            ->where('status', 'active')
            ->where('approval_status', 'approved')
            ->where(function ($q) use ($lot) {
                $q->whereNull('commodity_id')->orWhere('commodity_id', $lot->commodity_id);
            })
            ->orderByRaw('commodity_id is null')
            ->orderByDesc('id')
            ->first();
    }

    public function lotAlreadyInvoiced(StorageLot $lot, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): bool
    {
        return StorageInvoice::query()
            ->where('storage_lot_id', $lot->id)
            ->whereNotIn('status', ['cancelled'])
            ->when($periodStart && $periodEnd, function ($q) use ($periodStart, $periodEnd) {
                $q->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString());
            })
            ->exists();
    }

    public function createFromDelivery(User $actor, Branch $branch, Customer $customer, ?DeliveryOrder $order, float $amount, string $kind = 'tax'): StorageInvoice
    {
        abort_unless($this->canCreateInvoice($actor), 403);
        $this->assertBranchAccess($actor, (int) $branch->id);
        abort_unless((int) $customer->branch_id === (int) $branch->id, 403);
        if ($order) {
            abort_unless((int) $order->branch_id === (int) $branch->id, 403);
            abort_unless((int) $order->customer_id === (int) $customer->id, 422);
            $exists = StorageInvoice::query()
                ->where('delivery_order_id', $order->id)
                ->where('kind', $kind)
                ->whereNotIn('status', ['cancelled'])
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['delivery_order_id' => 'An invoice already exists for this delivery order.']);
            }
        }

        $invoice = StorageInvoice::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'delivery_order_id' => $order?->id,
            'storage_lot_id' => $order?->storage_lot_id,
            'number' => $this->nextNumber($branch, $kind),
            'kind' => $kind,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'amount' => $amount,
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);

        StorageInvoiceLine::query()->create([
            'tenant_id' => $branch->tenant_id,
            'storage_invoice_id' => $invoice->id,
            'storage_lot_id' => $order?->storage_lot_id,
            'charge_type' => 'dispatch',
            'description' => $order ? 'Delivery order #'.$order->id : 'Manual invoice',
            'quantity' => $order?->quantity ?? 1,
            'unit' => 'lot',
            'unit_rate' => $amount,
            'billable_days' => 1,
            'amount' => $amount,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'line_total' => $amount,
            'is_manual' => true,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('billing.invoice_created', $invoice, [], $branch->tenant_id, [
            'module' => 'billing', 'branch_id' => $branch->id,
            'new' => ['number' => $invoice->number, 'kind' => $kind, 'amount' => $amount],
        ]);

        return $invoice;
    }

    public function createFromLot(User $actor, StorageLot $lot, string $kind = 'tax', ?Carbon $periodStart = null, ?Carbon $periodEnd = null): StorageInvoice
    {
        abort_unless($this->canCreateInvoice($actor), 403);
        $this->assertBranchAccess($actor, (int) $lot->branch_id);
        $periodEnd ??= now();
        $periodStart ??= $lot->received_at ? Carbon::parse($lot->received_at) : now()->startOfMonth();
        if ($this->lotAlreadyInvoiced($lot)) {
            throw ValidationException::withMessages(['storage_lot_id' => 'Lot already invoiced for this period.']);
        }

        $accrual = $this->accrueLot($actor, $lot, $periodEnd);
        if ($accrual['total'] <= 0) {
            throw ValidationException::withMessages(['storage_lot_id' => 'No billable charges for this lot.']);
        }

        $branch = Branch::query()->findOrFail($lot->branch_id);

        return DB::transaction(function () use ($actor, $lot, $kind, $periodStart, $periodEnd, $accrual, $branch) {
            $invoice = StorageInvoice::query()->create([
                'tenant_id' => $lot->tenant_id,
                'branch_id' => $lot->branch_id,
                'customer_id' => $lot->customer_id,
                'storage_lot_id' => $lot->id,
                'number' => $this->nextNumber($branch, $kind),
                'kind' => $kind,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'subtotal' => $accrual['amount'],
                'tax_amount' => $accrual['tax_amount'],
                'amount' => $accrual['total'],
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            StorageInvoiceLine::query()->create([
                'tenant_id' => $lot->tenant_id,
                'storage_invoice_id' => $invoice->id,
                'storage_lot_id' => $lot->id,
                'rate_agreement_id' => $accrual['rate']?->id,
                'charge_type' => $accrual['rate']?->charge_type ?? 'storage',
                'description' => 'Storage '.$lot->lot_number,
                'quantity' => $accrual['quantity'],
                'unit' => $accrual['rate']?->unit ?? 'kg',
                'unit_rate' => $accrual['unit_rate'],
                'storage_days' => $accrual['storage_days'],
                'free_days' => $accrual['free_days'],
                'grace_days' => $accrual['grace_days'],
                'billable_days' => $accrual['billable_days'],
                'amount' => $accrual['amount'],
                'tax_percent' => $accrual['tax_percent'],
                'tax_amount' => $accrual['tax_amount'],
                'line_total' => $accrual['total'],
                'created_by' => $actor->id,
            ]);

            $this->audit->record('billing.invoice_created', $invoice, [], $lot->tenant_id, [
                'module' => 'billing', 'branch_id' => $lot->branch_id,
                'new' => ['number' => $invoice->number, 'kind' => $kind, 'lot' => $lot->lot_number],
            ]);

            return $invoice;
        });
    }

    public function updateDraft(User $actor, StorageInvoice $invoice, array $data): StorageInvoice
    {
        abort_unless($this->canEditDraft($actor), 403);
        $this->financialDocuments->assertMutable($invoice->status);
        abort_unless($invoice->isDraft(), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        $old = $invoice->only(['notes', 'due_date', 'discount_percent', 'discount_amount']);
        $invoice->update([
            'notes' => $data['notes'] ?? $invoice->notes,
            'due_date' => $data['due_date'] ?? $invoice->due_date,
        ]);
        $this->audit->record('billing.invoice_updated', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'old' => $old, 'new' => $invoice->fresh()->only(array_keys($old)),
        ]);

        return $invoice->fresh();
    }

    public function applyDiscount(User $actor, StorageInvoice $invoice, float $percent, string $reason): void
    {
        abort_unless($this->canApplyDiscount($actor), 403);
        $this->financialDocuments->assertMutable($invoice->status);
        abort_unless($invoice->isDraft(), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        $within = $this->limits->withinManagerLimit('discount_percent', $percent);
        if (! $within && ! $actor->isSuperAdmin()) {
            $invoice->update([
                'discount_percent' => $percent,
                'discount_reason' => $reason,
                'discount_status' => 'pending',
            ]);
            $this->audit->record('billing.discount_requested', $invoice, [], $invoice->tenant_id, [
                'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
                'new' => ['discount_percent' => $percent],
            ]);

            return;
        }

        $this->commitDiscount($actor, $invoice, $percent, $reason);
    }

    public function approveDiscount(User $actor, StorageInvoice $invoice, bool $approve = true): void
    {
        abort_unless($this->canApproveDiscount($actor) || ($actor->isSuperAdmin()), 403);
        abort_unless($invoice->discount_status === 'pending', 422);
        if (! $approve) {
            $invoice->update(['discount_status' => 'rejected', 'discount_percent' => 0, 'discount_amount' => 0]);
            $this->audit->record('billing.discount_rejected', $invoice, [], $invoice->tenant_id, [
                'module' => 'billing', 'branch_id' => $invoice->branch_id,
            ]);

            return;
        }
        $this->commitDiscount($actor, $invoice, (float) $invoice->discount_percent, (string) $invoice->discount_reason);
    }

    private function commitDiscount(User $actor, StorageInvoice $invoice, float $percent, string $reason): void
    {
        $subtotal = (float) $invoice->subtotal;
        $discount = round($subtotal * $percent / 100, 2);
        $taxable = max(0, $subtotal - $discount);
        $taxRatio = (float) $invoice->subtotal > 0 ? (float) $invoice->tax_amount / (float) $invoice->subtotal : 0;
        $tax = round($taxable * $taxRatio, 2);
        $invoice->update([
            'discount_percent' => $percent,
            'discount_amount' => $discount,
            'discount_reason' => $reason,
            'discount_status' => 'approved',
            'discount_approved_by' => $actor->id,
            'tax_amount' => $tax,
            'amount' => round($taxable + $tax, 2),
        ]);
        $this->audit->record('billing.discount_applied', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
            'new' => ['discount_percent' => $percent, 'discount_amount' => $discount],
        ]);
    }

    public function addManualCharge(User $actor, StorageInvoice $invoice, array $data): StorageInvoiceLine
    {
        abort_unless($this->canAddManualCharge($actor), 403);
        $this->financialDocuments->assertMutable($invoice->status);
        abort_unless($invoice->isDraft(), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        if ($actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
            // Manager approval path: treat as immediate add when approving own ops — still maker-checker if created by another.
        }

        $amount = round((float) $data['amount'], 2);
        $taxPercent = (float) ($data['tax_percent'] ?? 0);
        $tax = round($amount * $taxPercent / 100, 2);
        $line = StorageInvoiceLine::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'storage_invoice_id' => $invoice->id,
            'charge_type' => $data['charge_type'] ?? 'manual',
            'description' => $data['description'],
            'quantity' => 1,
            'unit' => 'ea',
            'unit_rate' => $amount,
            'billable_days' => 1,
            'amount' => $amount,
            'tax_percent' => $taxPercent,
            'tax_amount' => $tax,
            'line_total' => round($amount + $tax, 2),
            'is_manual' => true,
            'created_by' => $actor->id,
        ]);
        $this->recalcTotals($invoice);
        $this->audit->record('billing.manual_charge_added', $line, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $data['description'],
            'new' => ['amount' => $amount],
        ]);

        return $line;
    }

    public function recalcTotals(StorageInvoice $invoice): void
    {
        $subtotal = (float) $invoice->lines()->sum('amount');
        $tax = (float) $invoice->lines()->sum('tax_amount');
        $discount = (float) $invoice->discount_amount;
        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'amount' => max(0, round($subtotal + $tax - $discount, 2)),
        ]);
    }

    public function post(User $actor, StorageInvoice $invoice): void
    {
        abort_unless($this->canPost($actor), 403);
        $this->financialDocuments->assertMutable($invoice->status);
        abort_unless($invoice->isDraft(), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);
        if ($invoice->discount_status === 'pending') {
            throw ValidationException::withMessages(['discount' => 'Pending discount approval must be resolved first.']);
        }

        $invoice->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => $actor->id,
        ]);
        $this->audit->record('invoice.posted', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'new' => ['status' => 'posted'],
        ]);
    }

    public function requestCancellation(User $actor, StorageInvoice $invoice, string $reason): void
    {
        abort_unless($this->canRequestCancel($actor) || $this->canCancel($actor), 403);
        abort_unless($invoice->isPosted(), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        if ($this->canCancel($actor) && ! $actor->hasRole(RoleName::Accountant)) {
            $this->cancel($actor, $invoice, $reason);

            return;
        }

        $invoice->update([
            'cancel_status' => 'pending',
            'cancel_reason' => $reason,
            'cancel_requested_by' => $actor->id,
        ]);
        $this->audit->record('billing.cancel_requested', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
        ]);
    }

    public function approveCancellation(User $actor, StorageInvoice $invoice, bool $approve = true): void
    {
        abort_unless($this->canCancel($actor), 403);
        abort_unless($invoice->cancel_status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $invoice->cancel_requested_by);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        if (! $approve) {
            $invoice->update(['cancel_status' => 'rejected']);
            $this->audit->record('billing.cancel_rejected', $invoice, [], $invoice->tenant_id, [
                'module' => 'billing', 'branch_id' => $invoice->branch_id,
            ]);

            return;
        }

        $this->cancel($actor, $invoice, (string) $invoice->cancel_reason);
    }

    private function cancel(User $actor, StorageInvoice $invoice, string $reason): void
    {
        $invoice->update([
            'status' => 'cancelled',
            'cancel_status' => 'approved',
            'cancel_reason' => $reason,
            'cancel_approved_by' => $actor->id,
        ]);
        $this->audit->record('billing.invoice_cancelled', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
        ]);
    }

    public function createAdjustment(User $actor, StorageInvoice $invoice, string $kind, float $amount, string $reason): BillingAdjustment
    {
        abort_unless($this->canCreateAdjustment($actor), 403);
        abort_unless($invoice->isPosted(), 422);
        abort_unless(in_array($kind, ['credit', 'debit'], true), 422);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        $needsApproval = ! $actor->isSuperAdmin();
        $adjustment = BillingAdjustment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'branch_id' => $invoice->branch_id,
            'storage_invoice_id' => $invoice->id,
            'kind' => $kind,
            'number' => strtoupper($kind[0]).'N-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'amount' => $amount,
            'reason' => $reason,
            'status' => $needsApproval ? 'pending' : 'posted',
            'created_by' => $actor->id,
            'approved_by' => $needsApproval ? null : $actor->id,
            'posted_at' => $needsApproval ? null : now(),
        ]);

        if (! $needsApproval) {
            $this->applyAdjustment($invoice, $adjustment);
        }

        $this->audit->record('billing.adjustment_created', $adjustment, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
            'new' => ['kind' => $kind, 'amount' => $amount, 'status' => $adjustment->status],
        ]);

        return $adjustment;
    }

    public function approveAdjustment(User $actor, BillingAdjustment $adjustment, bool $approve = true): void
    {
        abort_unless($this->canApproveAdjustment($actor), 403);
        abort_unless($adjustment->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $adjustment->created_by);
        $this->assertBranchAccess($actor, (int) $adjustment->branch_id);

        if (! $approve) {
            $adjustment->update(['status' => 'rejected', 'approved_by' => $actor->id]);
            $this->audit->record('billing.adjustment_rejected', $adjustment, [], $adjustment->tenant_id, [
                'module' => 'billing', 'branch_id' => $adjustment->branch_id,
            ]);

            return;
        }

        $adjustment->update(['status' => 'posted', 'approved_by' => $actor->id, 'posted_at' => now()]);
        $this->applyAdjustment($adjustment->invoice, $adjustment);
        $this->audit->record('billing.adjustment_posted', $adjustment, [], $adjustment->tenant_id, [
            'module' => 'billing', 'branch_id' => $adjustment->branch_id,
        ]);
    }

    private function applyAdjustment(StorageInvoice $invoice, BillingAdjustment $adjustment): void
    {
        if ($adjustment->kind === 'credit') {
            $invoice->update(['credited_amount' => (float) $invoice->credited_amount + (float) $adjustment->amount]);
        } else {
            $invoice->update(['debited_amount' => (float) $invoice->debited_amount + (float) $adjustment->amount]);
        }
    }

    public function requestWaiver(User $actor, StorageInvoice $invoice, float $amount, string $reason): BillingWaiverRequest
    {
        abort_unless($this->canRequestWaiver($actor) || $this->canApproveWaiver($actor), 403);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);

        $waiver = BillingWaiverRequest::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'branch_id' => $invoice->branch_id,
            'storage_invoice_id' => $invoice->id,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('billing.waiver_requested', $waiver, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id, 'reason' => $reason,
            'new' => ['amount' => $amount],
        ]);

        return $waiver;
    }

    public function approveWaiver(User $actor, BillingWaiverRequest $waiver, bool $approve = true): void
    {
        abort_unless($this->canApproveWaiver($actor), 403);
        abort_unless($waiver->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $waiver->created_by);
        $this->assertBranchAccess($actor, (int) $waiver->branch_id);

        if (! $actor->isSuperAdmin()) {
            $this->limits->assertCanApprove($actor, 'waiver_amount', $waiver->amount);
        }

        if (! $approve) {
            $waiver->update(['status' => 'rejected', 'approved_by' => $actor->id]);
            $this->audit->record('billing.waiver_rejected', $waiver, [], $waiver->tenant_id, [
                'module' => 'billing', 'branch_id' => $waiver->branch_id,
            ]);

            return;
        }

        $waiver->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $invoice = $waiver->invoice;
        $adjustment = BillingAdjustment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'branch_id' => $invoice->branch_id,
            'storage_invoice_id' => $invoice->id,
            'kind' => 'credit',
            'number' => 'CN-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'amount' => $waiver->amount,
            'reason' => 'Waiver: '.$waiver->reason,
            'status' => 'posted',
            'created_by' => $waiver->created_by,
            'approved_by' => $actor->id,
            'posted_at' => now(),
        ]);
        $this->applyAdjustment($invoice, $adjustment);
        $this->audit->record('billing.waiver_approved', $waiver, [], $waiver->tenant_id, [
            'module' => 'billing', 'branch_id' => $waiver->branch_id, 'reason' => $waiver->reason,
        ]);
    }

    public function requestBulkRun(User $actor, Branch $branch, Carbon $start, Carbon $end): BillingBulkRun
    {
        abort_unless($this->canRequestBulk($actor), 403);
        $this->assertBranchAccess($actor, (int) $branch->id);

        $exists = BillingBulkRun::query()
            ->where('branch_id', $branch->id)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->whereNotIn('status', ['rejected'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['period' => 'A bulk run already exists for this period.']);
        }

        try {
            $run = BillingBulkRun::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['period' => 'A bulk run already exists for this period.']);
        }
        $this->audit->record('billing.bulk_requested', $run, [], $branch->tenant_id, [
            'module' => 'billing', 'branch_id' => $branch->id,
            'new' => ['period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
        ]);

        return $run;
    }

    public function approveBulkRun(User $actor, BillingBulkRun $run, bool $approve = true): void
    {
        abort_unless($this->canApproveBulk($actor), 403);
        abort_unless($run->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $run->created_by);
        $this->assertBranchAccess($actor, (int) $run->branch_id);

        if (! $approve) {
            $run->update(['status' => 'rejected', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record('billing.bulk_rejected', $run, [], $run->tenant_id, [
                'module' => 'billing', 'branch_id' => $run->branch_id,
            ]);

            return;
        }

        $created = 0;
        $skipped = 0;
        $lots = StorageLot::query()
            ->where('branch_id', $run->branch_id)
            ->whereIn('status', ['stored', 'closed', 'dispatched'])
            ->whereNotNull('received_at')
            ->whereDate('received_at', '<=', $run->period_end)
            ->get();

        foreach ($lots as $lot) {
            if ($this->lotAlreadyInvoiced($lot, $run->period_start, $run->period_end)) {
                $skipped++;
                continue;
            }
            try {
                $invoice = $this->createFromLot(
                    User::query()->findOrFail($run->created_by),
                    $lot,
                    'tax',
                    $run->period_start,
                    $run->period_end,
                );
                $invoice->update(['billing_bulk_run_id' => $run->id]);
                $created++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        $run->update([
            'status' => 'completed',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'summary' => ['created' => $created, 'skipped' => $skipped, 'lots_scanned' => $lots->count()],
        ]);
        $this->audit->record('billing.bulk_completed', $run, [], $run->tenant_id, [
            'module' => 'billing', 'branch_id' => $run->branch_id, 'new' => $run->summary,
        ]);
    }

    public function recordPrint(User $actor, StorageInvoice $invoice): void
    {
        abort_unless($this->canPrint($actor) || $this->canView($actor), 403);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);
        $this->audit->record('billing.invoice_printed', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id,
        ]);
    }

    public function recordSendAttempt(User $actor, StorageInvoice $invoice): array
    {
        abort_unless($this->canSend($actor), 403);
        $this->assertBranchAccess($actor, (int) $invoice->branch_id);
        $this->audit->record('billing.invoice_send_blocked', $invoice, [], $invoice->tenant_id, [
            'module' => 'billing', 'branch_id' => $invoice->branch_id,
            'reason' => 'Outbound email is not configured in this environment.',
        ]);

        return [
            'sent' => false,
            'message' => 'Invoice email was not sent: outbound mail / tax e-invoicing is not configured.',
        ];
    }

    private function nextNumber(Branch $branch, string $kind): string
    {
        $prefix = $kind === 'proforma' ? 'PF' : 'INV';

        return $prefix.'-'.$branch->code.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
    }
}
