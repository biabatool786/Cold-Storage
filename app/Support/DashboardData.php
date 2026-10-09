<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Csms\DeliveryOrder;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\OperatingExpense;
use App\Models\Csms\Booking;
use App\Models\Csms\StorageInvoice;
use App\Models\Csms\StorageLot;
use App\Models\Csms\StoragePayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardData
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user, ?string $status = null): array
    {
        $role = $this->primaryRole($user);

        return match ($role) {
            RoleName::SuperAdmin => $this->superAdmin($status),
            RoleName::Admin => $this->kpiDashboard('tenant-admin', [
                ['label' => 'Customers', 'value' => (string) $this->visibleCount(\App\Models\Csms\Customer::class, $user), 'hint' => 'Parties for this cold store'],
                ['label' => 'Open gates', 'value' => (string) $this->visibleCount(GateEntry::class, $user, 'open'), 'hint' => 'Vehicles still on site'],
                ['label' => 'Stored lots', 'value' => (string) $this->visibleCount(StorageLot::class, $user, 'stored'), 'hint' => 'Lots in chambers'],
                ['label' => 'Pending receipts', 'value' => (string) $this->visibleCount(GoodsReceipt::class, $user, 'pending'), 'hint' => 'GRNs waiting for approval'],
            ], $user),
            RoleName::Manager => $this->kpiDashboard('manager', [
                ['label' => 'Stored lots', 'value' => (string) $this->visibleCount(StorageLot::class, $user, 'stored'), 'hint' => 'Quantity on hand by lot'],
                ['label' => 'Open gates', 'value' => (string) $this->visibleCount(GateEntry::class, $user, 'open'), 'hint' => 'Vehicles on site'],
                ['label' => 'Pending receipts', 'value' => (string) $this->visibleCount(GoodsReceipt::class, $user, 'pending'), 'hint' => 'GRNs to approve'],
                ['label' => 'Pending dispatch', 'value' => (string) $this->visibleCount(DeliveryOrder::class, $user, 'pending'), 'hint' => 'Delivery orders to approve'],
                ['label' => 'Pending Approvals', 'value' => (string) $this->pendingApprovals($user), 'hint' => 'Bookings, receipts, dispatch, and expenses'],
            ]),
            RoleName::Supervisor => $this->kpiDashboard('manager', [
                ['label' => 'Open gates', 'value' => (string) $this->visibleCount(GateEntry::class, $user, 'open'), 'hint' => 'Arrivals to inspect'],
                ['label' => 'Stored lots', 'value' => (string) $this->visibleCount(StorageLot::class, $user, 'stored'), 'hint' => 'Lots to pick and load'],
                ['label' => 'Pending receipts', 'value' => (string) $this->visibleCount(GoodsReceipt::class, $user, 'pending'), 'hint' => 'Draft receipts you raised'],
            ]),
            RoleName::Gatekeeper => $this->kpiDashboard('manager', [
                ['label' => 'Open gates', 'value' => (string) $this->visibleCount(GateEntry::class, $user, 'open'), 'hint' => 'Vehicles still inside'],
                ['label' => 'Weighments', 'value' => (string) $this->visibleCount(\App\Models\Csms\Weighment::class, $user), 'hint' => 'Gross and tare tickets'],
                ['label' => 'Gate passes', 'value' => (string) $this->visibleCount(\App\Models\Csms\GatePass::class, $user, 'issued'), 'hint' => 'Waiting for release'],
            ]),
            RoleName::Accountant => $this->kpiDashboard('manager', [
                ['label' => 'Draft invoices', 'value' => (string) $this->visibleCount(StorageInvoice::class, $user, 'draft'), 'hint' => 'Not yet posted'],
                ['label' => 'Posted invoices', 'value' => (string) $this->visibleCount(StorageInvoice::class, $user, 'posted'), 'hint' => 'Locked financial documents'],
                ['label' => 'Payments', 'value' => (string) $this->visibleCount(StoragePayment::class, $user, 'posted'), 'hint' => 'Posted receipts'],
                ['label' => 'Expenses', 'value' => (string) $this->visibleCount(OperatingExpense::class, $user, 'pending'), 'hint' => 'Waiting for a manager'],
            ]),
            default => $this->modules('Overview', 'Your account is limited to this tenant. Operating modules stay with the roles that run them.', []),
        };
    }

    private function visibleCount(string $model, User $user, ?string $status = null): int
    {
        $query = $model::query()->visibleTo($user);
        if ($status !== null) {
            $query->where($model::make()->getTable().'.status', $status);
        }

        return $query->count();
    }

    private function pendingApprovals(User $user): int
    {
        return $this->visibleCount(Booking::class, $user, 'pending')
            + $this->visibleCount(GoodsReceipt::class, $user, 'pending')
            + $this->visibleCount(DeliveryOrder::class, $user, 'pending')
            + $this->visibleCount(OperatingExpense::class, $user, 'pending');
    }

    private function primaryRole(User $user): ?RoleName
    {
        foreach (RoleName::cases() as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function superAdmin(?string $status): array
    {
        $filter = TenantStatus::tryFrom((string) $status);

        $tenants = Tenant::query()
            ->withCount('users')
            ->when($filter, fn ($query) => $query->where('status', $filter))
            ->latest()
            ->limit(8)
            ->get();

        return [
            'view' => 'super-admin',
            'stats' => [
                ['label' => 'Tenants', 'value' => Tenant::query()->count(), 'hint' => 'Cold storage companies'],
                ['label' => 'Users', 'value' => User::query()->count(), 'hint' => 'Platform and tenant accounts'],
                ['label' => 'Branches', 'value' => \App\Models\Branch::query()->count(), 'hint' => 'Sites under every cold store'],
                ['label' => 'Open gates', 'value' => GateEntry::query()->where('status', 'open')->count(), 'hint' => 'Vehicles on site across the platform'],
            ],
            'tenants' => $tenants,
            'status' => $filter?->value,
            'users' => $this->recentUsers(),
            'pending' => GoodsReceipt::query()->where('status', 'pending')->count()
                + DeliveryOrder::query()->where('status', 'pending')->count()
                + OperatingExpense::query()->where('status', 'pending')->count(),
        ];
    }

    /**
     * @param  list<array{label: string, value: string, hint: string}>  $stats
     * @return array<string, mixed>
     */
    private function kpiDashboard(string $view, array $stats, ?User $user = null): array
    {
        return [
            'view' => $view,
            'stats' => $stats,
            'subscription' => null,
            'users' => $view === 'tenant-admin' ? $this->recentUsers($user) : collect(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  Collection<int, User>|null  $users
     * @return array<string, mixed>
     */
    private function modules(string $title, string $intro, array $sections, ?Collection $users = null): array
    {
        return [
            'view' => 'modules',
            'title' => $title,
            'intro' => $intro,
            'sections' => $sections,
            'users' => $users ?? collect(),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @return array{id: string, title: string, note: string, columns: list<string>}
     */
    private function section(string $id, string $title, string $note, array $columns): array
    {
        return compact('id', 'title', 'note', 'columns');
    }

    /**
     * @return Collection<int, User>
     */
    private function recentUsers(?User $actor = null): Collection
    {
        return User::query()
            ->with(['roles', 'tenant'])
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->id))
            ->latest()
            ->limit(6)
            ->get();
    }
}
