<x-layouts.authenticated :title="'Confirmation '.$booking->id">
    <div class="mb-6 flex items-end justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Booking confirmation #{{ $booking->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $booking->branch?->name }} · {{ $booking->status }}</p>
        </div>
        <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
    <section class="rounded-2xl border border-line bg-panel p-5 text-sm">
        <p>Customer {{ $booking->customer?->name }}</p>
        <p class="mt-2">Product {{ $booking->commodity?->name }}</p>
        <p class="mt-2">Quantity {{ $booking->quantity }} · Rate {{ $booking->rate ?? '—' }}</p>
        <p class="mt-2">Chamber {{ $booking->chamber?->code ?? '—' }}</p>
        <p class="mt-2">{{ $booking->starts_on?->toDateString() ?? '—' }} – {{ $booking->ends_on?->toDateString() ?? '—' }}</p>
        <p class="mt-2">{{ $booking->notes }}</p>
    </section>
</x-layouts.authenticated>
