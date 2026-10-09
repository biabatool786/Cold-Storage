<x-layouts.authenticated :title="'Token '.$entry->token">
    <div class="mb-6 flex items-end justify-between">
        <h2 class="text-2xl font-semibold tracking-tight">Entry token {{ $entry->token }}</h2>
        <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
    <section class="rounded-2xl border border-line bg-panel p-5 text-sm">
        <p>{{ $entry->vehicle_no }} · {{ $entry->driver_name ?? 'Driver not recorded' }}</p>
        <p class="mt-2">{{ $entry->purpose }} · {{ $entry->entered_at }}</p>
    </section>
</x-layouts.authenticated>
