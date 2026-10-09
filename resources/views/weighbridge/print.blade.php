<x-layouts.authenticated :title="'Slip '.$ticket->number">
    <div class="mb-6 flex items-end justify-between">
        <h2 class="text-2xl font-semibold tracking-tight">Weight slip {{ $ticket->number }}</h2>
        <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
    <section class="rounded-2xl border border-line bg-panel p-5 text-sm">
        <p>Vehicle {{ $ticket->vehicle_no }}</p>
        <p class="mt-2">{{ $ticket->weighed_at ?? $ticket->created_at }}</p>
        <p class="mt-2">Gross {{ $ticket->gross_weight ?? '—' }} · Tare {{ $ticket->tare_weight ?? '—' }} · Net {{ $ticket->net_weight ?? '—' }}</p>
        <p class="mt-2">Branch {{ $ticket->branch?->name }} · Operator #{{ $ticket->created_by }}</p>
        <p class="mt-2">Gate #{{ $ticket->gate_entry_id }} · Booking #{{ $ticket->booking_id ?? '—' }}</p>
    </section>
</x-layouts.authenticated>
