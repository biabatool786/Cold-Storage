<x-layouts.authenticated :title="'Booking '.$booking->id">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Booking #{{ $booking->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $booking->customer?->name }} · {{ $booking->commodity?->name }} · {{ $booking->status }}</p>
        </div>
        @if (auth()->user()->hasPermission('bookings.print'))
            <a class="btn-ghost" href="{{ route('bookings.print', $booking) }}">Print confirmation</a>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Request</h3>
            <p class="mt-2 text-sm">Quantity {{ $booking->quantity }} · Rate {{ $booking->rate ?? '—' }} · Chamber {{ $booking->chamber?->code ?? '—' }}</p>
            <p class="mt-1 text-sm">{{ $booking->starts_on?->toDateString() ?? '—' }} – {{ $booking->ends_on?->toDateString() ?? '—' }} · {{ $booking->reference }}</p>
            <p class="mt-1 text-sm text-muted">{{ $booking->notes }}</p>
            @if ($reservations->canEditOpen(auth()->user(), $booking) || ($reservations->canRevise(auth()->user()) && in_array($booking->status, ['approved', 'confirmed'], true)))
                <form method="POST" action="{{ route('bookings.update', $booking) }}" class="mt-4 grid gap-2">
                    @csrf @method('PUT')
                    <select name="customer_id" class="field">@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected($customer->id === $booking->customer_id)>{{ $customer->name }}</option>@endforeach</select>
                    <select name="commodity_id" class="field">@foreach ($commodities as $commodity)<option value="{{ $commodity->id }}" @selected($commodity->id === $booking->commodity_id)>{{ $commodity->name }}</option>@endforeach</select>
                    <select name="chamber_id" class="field"><option value="">Chamber</option>@foreach ($chambers as $chamber)<option value="{{ $chamber->id }}" @selected($chamber->id === $booking->chamber_id)>{{ $chamber->code }}</option>@endforeach</select>
                    <select name="rate_agreement_id" class="field"><option value="">Rate agreement</option>@foreach ($rates as $rate)<option value="{{ $rate->id }}" @selected($rate->id === $booking->rate_agreement_id)>{{ $rate->rate }}</option>@endforeach</select>
                    <input name="quantity" type="number" min="0.001" step="0.001" value="{{ $booking->quantity }}" class="field">
                    <input name="rate" type="number" min="0" step="0.01" value="{{ $booking->rate }}" placeholder="Rate" class="field">
                    <input name="vehicle_no" value="{{ $booking->vehicle_no }}" placeholder="Vehicle number" class="field">
                    <input name="driver_name" value="{{ $booking->driver_name }}" placeholder="Driver" class="field">
                    <input name="notes" value="{{ $booking->notes }}" placeholder="Notes" class="field">
                    @if (in_array($booking->status, ['approved', 'confirmed'], true))
                        <input name="reason" required placeholder="Reason" class="field">
                    @endif
                    <button class="btn-primary" type="submit">Save booking</button>
                </form>
            @endif
            @if ($booking->status === 'pending' && $reservations->canApprove(auth()->user()))
                <form method="POST" action="{{ route('bookings.approve', $booking) }}" class="mt-3 inline">@csrf<button class="btn-primary" type="submit">Approve</button></form>
                <form method="POST" action="{{ route('bookings.reject', $booking) }}" class="mt-3 flex gap-2">@csrf<input name="reason" required placeholder="Reason" class="field"><button class="btn-ghost" type="submit">Reject</button></form>
            @endif
            @if ($booking->status === 'approved' && auth()->user()->hasPermission('bookings.print'))
                <form method="POST" action="{{ route('bookings.confirm', $booking) }}" class="mt-3">@csrf<button class="btn-ghost" type="submit">Confirm</button></form>
            @endif
            @if ($reservations->canCancel(auth()->user()) && $booking->status !== 'cancelled')
                <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="mt-3 flex gap-2">@csrf<input name="reason" required placeholder="Cancel reason" class="field"><button class="btn-ghost" type="submit">Cancel</button></form>
            @endif
            @if ($booking->status === 'cancelled')
                <p class="mt-3 text-sm">Cancelled {{ $booking->cancelled_at }} · {{ $booking->cancel_reason }}</p>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Deposit</h3>
            @foreach ($booking->deposits as $deposit)
                <p class="mt-2 text-sm">{{ $deposit->amount }} @if($deposit->note) · {{ $deposit->note }} @endif</p>
            @endforeach
            @if ($reservations->canDeposit(auth()->user()) && $booking->status !== 'cancelled')
                <form method="POST" action="{{ route('bookings.deposit', $booking) }}" class="mt-4 flex gap-2">@csrf<input name="amount" type="number" min="0.01" step="0.01" required placeholder="Amount" class="field"><input name="note" placeholder="Note" class="field"><button class="btn-ghost" type="submit">Record deposit</button></form>
            @endif
            <h3 class="mt-6 font-semibold">Rate changes</h3>
            @foreach ($booking->revisions as $revision)
                <p class="mt-2 text-sm">{{ $revision->rate }} · {{ $revision->status }}
                    @if ($revision->status === 'pending' && $reservations->canApprove(auth()->user()))
                        <form method="POST" action="{{ route('bookings.revisions.decide', $revision) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost px-2 py-1" type="submit">Approve</button></form>
                    @endif
                </p>
            @endforeach
        </section>
    </div>
</x-layouts.authenticated>
