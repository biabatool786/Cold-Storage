<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\BookingRevision;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\RateAgreement;
use App\Models\User;
use App\Services\Bookings\Reservation;
use App\Services\Operations\GoodsFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private readonly Reservation $reservations, private readonly GoodsFlow $flow) {}

    public function index(Request $request): View
    {
        return $this->list($request, null, 'bookings.index', 'Bookings');
    }

    public function create(Request $request, Branch $branch): View
    {
        abort_unless($request->user()->hasPermission('bookings.view') || $this->reservations->canCreate($request->user()), 403);
        $this->reservations->guard($request->user(), $branch);

        return $this->list($request, null, 'bookings.index', 'Bookings', $branch);
    }

    public function waiting(Request $request): View
    {
        return $this->list($request, 'waiting', 'bookings.waiting', 'Waiting list');
    }

    public function availability(Request $request): View
    {
        return view('bookings.availability', [
            'rows' => $this->reservations->availability($request->user()),
        ]);
    }

    public function contracts(Request $request): View
    {
        abort_unless($request->user()->hasPermission('bookings.view'), 403);
        $user = $request->user();

        return view('bookings.contracts', [
            'contracts' => RateAgreement::query()->visibleTo($user)->where('kind', 'seasonal')->latest()->limit(50)->get(),
            'reservations' => $this->reservations,
            'branches' => $this->branches($user),
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'branch_id']),
            'commodities' => Commodity::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $booking = $this->reservations->place($request->user(), $branch, $this->validated($request));

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking requested.');
    }

    public function show(Request $request, Booking $booking): View
    {
        $this->see($request->user(), $booking);
        $booking->load(['customer', 'commodity', 'variety', 'grade', 'chamber', 'rateAgreement', 'deposits', 'revisions']);

        return view('bookings.show', [
            'booking' => $booking,
            'reservations' => $this->reservations,
            'customers' => Customer::query()->visibleTo($request->user())->where('branch_id', $booking->branch_id)->orderBy('name')->get(),
            'commodities' => Commodity::query()->orderBy('name')->get(),
            'chambers' => Chamber::query()->visibleTo($request->user())->where('branch_id', $booking->branch_id)->orderBy('code')->get(),
            'rates' => RateAgreement::query()->where('branch_id', $booking->branch_id)->where('status', 'active')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $this->validated($request);
        if (in_array($booking->status, ['approved', 'confirmed'], true)) {
            $this->reservations->revise($request->user(), $booking, $data, $request->input('reason'));
        } else {
            $this->reservations->updateOpen($request->user(), $booking, $data);
        }

        return back()->with('status', 'Booking saved.');
    }

    public function approve(Booking $booking): RedirectResponse
    {
        $this->flow->approveBooking(request()->user(), $booking);

        return back()->with('status', 'Booking approved.');
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->reservations->reject($request->user(), $booking, $data['reason'] ?? null);

        return back()->with('status', 'Booking rejected.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->reservations->cancel($request->user(), $booking, $data['reason'] ?? null);

        return back()->with('status', 'Booking cancelled.');
    }

    public function queue(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['waiting' => ['required', 'boolean']]);
        $this->reservations->queue($request->user(), $booking, $request->boolean('waiting'));

        return back()->with('status', 'Waiting list updated.');
    }

    public function deposit(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->reservations->deposit($request->user(), $booking, (float) $data['amount'], $data['note'] ?? null);

        return back()->with('status', 'Deposit recorded.');
    }

    public function decideRevision(Request $request, BookingRevision $booking_revision): RedirectResponse
    {
        $request->validate(['approve' => ['required', 'boolean']]);
        $this->reservations->decideRevision($request->user(), $booking_revision, $request->boolean('approve'));

        return back()->with('status', 'Rate change decision saved.');
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $this->reservations->confirm($request->user(), $booking);

        return back()->with('status', 'Booking confirmed.');
    }

    public function print(Request $request, Booking $booking): View
    {
        $this->reservations->printed($request->user(), $booking);
        $booking->load(['customer', 'commodity', 'chamber', 'branch']);

        return view('bookings.print', ['booking' => $booking]);
    }

    public function storeContract(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'commodity_id' => ['nullable', 'integer'],
            'rate' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);
        $this->reservations->createContract($request->user(), $branch, $data);

        return back()->with('status', 'Seasonal contract submitted.');
    }

    public function decideContract(Request $request, RateAgreement $rate_agreement): RedirectResponse
    {
        $request->validate(['approve' => ['required', 'boolean']]);
        $this->reservations->decideContract($request->user(), $rate_agreement, $request->boolean('approve'));

        return back()->with('status', 'Seasonal contract decision saved.');
    }

    private function list(Request $request, ?string $status, string $view, string $title, ?Branch $branch = null): View
    {
        abort_unless($request->user()->hasPermission('bookings.view'), 403);
        $user = $request->user();
        $bookings = Booking::query()->visibleTo($user)->with(['customer', 'commodity', 'chamber'])
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()->limit(50)->get();

        return view($view === 'bookings.waiting' ? 'bookings.waiting' : 'bookings.index', [
            'title' => $title,
            'bookings' => $bookings,
            'reservations' => $this->reservations,
            'branches' => $branch ? collect([$branch]) : $this->branches($user),
            'customers' => Customer::query()->visibleTo($user)->when($branch, fn ($query) => $query->where('branch_id', $branch->id))->orderBy('name')->get(),
            'commodities' => Commodity::query()->visibleTo($user)->when($branch, fn ($query) => $query->where('tenant_id', $branch->tenant_id))->orderBy('name')->get(),
            'chambers' => Chamber::query()->visibleTo($user)->when($branch, fn ($query) => $query->where('branch_id', $branch->id))->orderBy('code')->get(),
            'rates' => RateAgreement::query()->visibleTo($user)->where('status', 'active')->when($branch, fn ($query) => $query->where('branch_id', $branch->id))->orderBy('id')->get(),
        ]);
    }

    private function see(User $user, Booking $booking): void
    {
        abort_unless($user->hasPermission('bookings.view'), 403);
        $this->reservations->guard($user, $booking->branch);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'commodity_id' => ['required', 'integer'],
            'commodity_variety_id' => ['nullable', 'integer'],
            'commodity_grade_id' => ['nullable', 'integer'],
            'chamber_id' => ['nullable', 'integer'],
            'rate_agreement_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'vehicle_no' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        unset($data['tenant_id'], $data['branch_id'], $data['reason']);

        return $data;
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
