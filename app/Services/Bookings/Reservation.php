<?php

namespace App\Services\Bookings;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\BookingDeposit;
use App\Models\Csms\BookingRevision;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\CommodityGrade;
use App\Models\Csms\CommodityVariety;
use App\Models\Csms\Customer;
use App\Models\Csms\RateAgreement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use App\Services\Authorization\MakerChecker;
use App\Services\Authorization\ReasonRequirement;
use App\Services\Notifications\OperationalAlertHooks;
use Illuminate\Support\Collection;

class Reservation
{
    public const RESERVED = ['pending', 'approved', 'confirmed'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly MakerChecker $makerChecker,
        private readonly ReasonRequirement $reasons,
        private readonly OperationalAlertHooks $operationalAlerts,
    ) {}

    public function canCreate(User $user): bool
    {
        return $user->hasPermission('bookings.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor]));
    }

    public function canEditOpen(User $user, Booking $booking): bool
    {
        if (! $user->hasPermission('bookings.edit') || ! in_array($booking->status, ['pending', 'waiting'], true)) {
            return false;
        }
        if ($user->hasRole(RoleName::Supervisor) && ! $user->isSuperAdmin()) {
            return (int) $booking->created_by === (int) $user->id;
        }

        return $user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager, RoleName::Supervisor]);
    }

    public function canApprove(User $user): bool
    {
        return $user->hasPermission('bookings.approve')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canDeposit(User $user): bool
    {
        return $user->hasPermission('bookings.deposit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Accountant));
    }

    public function canRevise(User $user): bool
    {
        return $user->hasPermission('bookings.edit')
            && ($user->isSuperAdmin() || $user->hasRole(RoleName::Manager));
    }

    public function canCancel(User $user): bool
    {
        return $this->canApprove($user);
    }

    public function canManageQueue(User $user): bool
    {
        return $user->hasPermission('bookings.execute')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function canCreateContract(User $user): bool
    {
        return $user->hasPermission('bookings.create')
            && ($user->isSuperAdmin() || $user->hasAnyRole([RoleName::Admin, RoleName::Manager]));
    }

    public function guard(User $actor, Branch $branch): void
    {
        abort_unless($this->branches->allows($actor, $branch), 403);
    }

    public function reservedQuantity(?int $chamberId, ?int $exceptBookingId = null): float
    {
        return (float) Booking::query()
            ->when($chamberId, fn ($query) => $query->where('chamber_id', $chamberId))
            ->whereIn('status', self::RESERVED)
            ->when($exceptBookingId, fn ($query) => $query->whereKeyNot($exceptBookingId))
            ->sum('quantity');
    }

    public function available(?float $capacity, float $reserved): ?float
    {
        return $capacity === null ? null : $capacity - $reserved;
    }

    /**
     * @return Collection<int, array{chamber: Chamber, reserved: float, available: ?float}>
     */
    public function availability(User $actor): Collection
    {
        abort_unless($actor->hasPermission('bookings.view'), 403);

        return Chamber::query()->visibleTo($actor)->orderBy('code')->get()->map(function (Chamber $chamber) {
            $reserved = $this->reservedQuantity($chamber->id);

            return [
                'chamber' => $chamber,
                'reserved' => $reserved,
                'available' => $this->available($chamber->capacity === null ? null : (float) $chamber->capacity, $reserved),
            ];
        });
    }

    public function place(User $actor, Branch $branch, array $data): Booking
    {
        abort_unless($this->canCreate($actor), 403);
        $this->guard($actor, $branch);
        $this->assertLinks($branch, $data);
        $status = 'pending';
        if (! empty($data['chamber_id'])) {
            $chamber = Chamber::query()->findOrFail($data['chamber_id']);
            $open = $this->available($chamber->capacity === null ? null : (float) $chamber->capacity, $this->reservedQuantity($chamber->id));
            if ($open !== null && (float) $data['quantity'] > $open) {
                $status = 'waiting';
            }
        }
        $booking = Booking::query()->create([
            ...$this->fields($data),
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'status' => $status,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('booking.requested', $booking, ['status' => $status], $branch->tenant_id, [
            'module' => 'bookings', 'branch_id' => $branch->id,
        ]);

        if ($status === 'pending') {
            $this->operationalAlerts->pendingApproval(
                $booking,
                (int) $booking->tenant_id,
                (int) $booking->branch_id,
                'Booking',
                'Booking #'.$booking->id.' awaits approval.'
            );
        }
        if (! empty($data['chamber_id'])) {
            $this->operationalAlerts->checkChamberOccupancy(
                Chamber::query()->findOrFail($data['chamber_id'])
            );
        }

        return $booking;
    }

    public function updateOpen(User $actor, Booking $booking, array $data): void
    {
        abort_unless($this->canEditOpen($actor, $booking), 403);
        $this->guard($actor, $booking->branch);
        $data['customer_id'] = $data['customer_id'] ?? $booking->customer_id;
        $data['commodity_id'] = $data['commodity_id'] ?? $booking->commodity_id;
        $this->assertLinks($booking->branch, $data);
        $booking->update($this->fields($data));
        $this->audit->record('booking.updated', $booking, [], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function revise(User $actor, Booking $booking, array $data, ?string $reason): void
    {
        abort_unless(in_array($booking->status, ['approved', 'confirmed'], true), 422);
        abort_unless($this->canRevise($actor), 403);
        $this->guard($actor, $booking->branch);
        $this->reasons->assert('edit_after_approval', $reason);
        $rateChanged = array_key_exists('rate', $data) && (string) $data['rate'] !== (string) $booking->rate
            || array_key_exists('rate_agreement_id', $data) && (int) $data['rate_agreement_id'] !== (int) $booking->rate_agreement_id;
        if ($rateChanged && $actor->hasRole(RoleName::Manager) && ! $actor->isSuperAdmin()) {
            $revision = BookingRevision::query()->create([
                'tenant_id' => $booking->tenant_id,
                'branch_id' => $booking->branch_id,
                'booking_id' => $booking->id,
                'rate' => $data['rate'] ?? $booking->rate,
                'rate_agreement_id' => $data['rate_agreement_id'] ?? $booking->rate_agreement_id,
                'status' => 'pending',
                'created_by' => $actor->id,
            ]);
            $this->audit->record('booking.rate_change_proposed', $revision, [], $booking->tenant_id, [
                'module' => 'bookings', 'branch_id' => $booking->branch_id, 'reason' => $reason,
            ]);

            return;
        }
        $booking->update($this->fields($data));
        $this->audit->record('booking.revised', $booking, ['reason' => $reason], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function decideRevision(User $actor, BookingRevision $revision, bool $approve): void
    {
        abort_unless($this->canApprove($actor), 403);
        abort_unless($revision->status === 'pending', 422);
        $this->makerChecker->assertCanApprove($actor, $revision->created_by);
        $this->guard($actor, $revision->booking->branch);
        $revision->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $actor->id]);
        if ($approve) {
            $revision->booking->update(['rate' => $revision->rate, 'rate_agreement_id' => $revision->rate_agreement_id]);
        }
        $this->audit->record($approve ? 'booking.rate_change_approved' : 'booking.rate_change_rejected', $revision, [], $revision->tenant_id, [
            'module' => 'bookings', 'branch_id' => $revision->branch_id,
        ]);
    }

    public function reject(User $actor, Booking $booking, ?string $reason): void
    {
        abort_unless($booking->status === 'pending', 422);
        abort_unless($this->canApprove($actor), 403);
        $this->makerChecker->assertCanApprove($actor, $booking->created_by);
        $this->guard($actor, $booking->branch);
        $this->reasons->assert('override', $reason);
        $booking->update(['status' => 'rejected']);
        $this->audit->record('booking.rejected', $booking, ['reason' => $reason], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function cancel(User $actor, Booking $booking, ?string $reason): void
    {
        abort_unless($this->canCancel($actor), 403);
        abort_if($booking->status === 'cancelled', 422);
        $this->guard($actor, $booking->branch);
        $this->reasons->assert('override', $reason);
        $booking->update([
            'status' => 'cancelled',
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        $this->audit->record('booking.cancelled', $booking, ['reason' => $reason], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function queue(User $actor, Booking $booking, bool $waiting): void
    {
        abort_unless($this->canManageQueue($actor), 403);
        $this->guard($actor, $booking->branch);
        if ($waiting) {
            abort_unless(in_array($booking->status, ['pending', 'waiting'], true), 422);
            $booking->update(['status' => 'waiting']);
        } else {
            abort_unless($booking->status === 'waiting', 422);
            $open = null;
            if ($booking->chamber_id) {
                $capacity = $booking->chamber?->capacity;
                $open = $this->available($capacity === null ? null : (float) $capacity, $this->reservedQuantity($booking->chamber_id));
            }
            abort_if($open !== null && (float) $booking->quantity > $open, 422);
            $booking->update(['status' => 'pending']);
        }
        $this->audit->record($waiting ? 'booking.waitlisted' : 'booking.waitlist_released', $booking, [], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function deposit(User $actor, Booking $booking, float $amount, ?string $note): void
    {
        abort_unless($this->canDeposit($actor), 403);
        $this->guard($actor, $booking->branch);
        abort_if($booking->status === 'cancelled', 422);
        $deposit = BookingDeposit::query()->create([
            'tenant_id' => $booking->tenant_id,
            'branch_id' => $booking->branch_id,
            'booking_id' => $booking->id,
            'amount' => $amount,
            'note' => $note,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('booking.deposit_recorded', $deposit, ['amount' => $amount], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function confirm(User $actor, Booking $booking): void
    {
        abort_unless($actor->hasPermission('bookings.print'), 403);
        abort_unless($booking->status === 'approved', 422);
        $this->guard($actor, $booking->branch);
        $booking->update(['status' => 'confirmed']);
        $this->audit->record('booking.confirmed', $booking, [], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function printed(User $actor, Booking $booking): void
    {
        abort_unless($actor->hasPermission('bookings.print'), 403);
        $this->guard($actor, $booking->branch);
        $this->audit->record('booking.printed', $booking, [], $booking->tenant_id, [
            'module' => 'bookings', 'branch_id' => $booking->branch_id,
        ]);
    }

    public function createContract(User $actor, Branch $branch, array $data): RateAgreement
    {
        abort_unless($this->canCreateContract($actor), 403);
        $this->guard($actor, $branch);
        $this->assertLinks($branch, $data);
        $agreement = RateAgreement::query()->create([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'customer_id' => $data['customer_id'],
            'commodity_id' => $data['commodity_id'] ?? null,
            'rate' => $data['rate'],
            'unit' => $data['unit'] ?? 'kg',
            'kind' => 'seasonal',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'status' => 'inactive',
            'season_status' => 'pending',
            'created_by' => $actor->id,
        ]);
        $this->audit->record('booking.contract_proposed', $agreement, [], $branch->tenant_id, [
            'module' => 'bookings', 'branch_id' => $branch->id,
        ]);

        return $agreement;
    }

    public function decideContract(User $actor, RateAgreement $agreement, bool $approve): void
    {
        abort_unless($agreement->kind === 'seasonal' && $agreement->season_status === 'pending', 422);
        abort_unless($this->canApprove($actor), 403);
        $this->makerChecker->assertCanApprove($actor, $agreement->created_by);
        $this->guard($actor, $agreement->branch);
        $agreement->update([
            'season_status' => $approve ? 'approved' : 'rejected',
            'status' => $approve ? 'active' : 'inactive',
            'approved_by' => $actor->id,
        ]);
        $this->audit->record($approve ? 'booking.contract_approved' : 'booking.contract_rejected', $agreement, [], $agreement->tenant_id, [
            'module' => 'bookings', 'branch_id' => $agreement->branch_id,
        ]);
    }

    private function fields(array $data): array
    {
        return [
            'customer_id' => $data['customer_id'],
            'commodity_id' => $data['commodity_id'],
            'commodity_variety_id' => $data['commodity_variety_id'] ?? null,
            'commodity_grade_id' => $data['commodity_grade_id'] ?? null,
            'chamber_id' => $data['chamber_id'] ?? null,
            'rate_agreement_id' => $data['rate_agreement_id'] ?? null,
            'quantity' => $data['quantity'],
            'rate' => $data['rate'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'vehicle_no' => $data['vehicle_no'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
        ];
    }

    private function assertLinks(Branch $branch, array $data): void
    {
        abort_unless(Customer::query()->whereKey($data['customer_id'])->where('branch_id', $branch->id)->exists(), 404);
        if (! empty($data['commodity_id'])) {
            abort_unless(Commodity::query()->whereKey($data['commodity_id'])->exists(), 404);
        }
        if (! empty($data['commodity_variety_id'])) {
            abort_unless(CommodityVariety::query()->whereKey($data['commodity_variety_id'])->where('commodity_id', $data['commodity_id'])->exists(), 422);
        }
        if (! empty($data['commodity_grade_id'])) {
            abort_unless(CommodityGrade::query()->whereKey($data['commodity_grade_id'])->where('commodity_variety_id', $data['commodity_variety_id'] ?? 0)->exists(), 422);
        }
        if (! empty($data['chamber_id'])) {
            abort_unless(Chamber::query()->whereKey($data['chamber_id'])->where('branch_id', $branch->id)->exists(), 404);
        }
        if (! empty($data['rate_agreement_id'])) {
            abort_unless(RateAgreement::query()->whereKey($data['rate_agreement_id'])->where('branch_id', $branch->id)->where('customer_id', $data['customer_id'])->exists(), 422);
        }
    }
}
