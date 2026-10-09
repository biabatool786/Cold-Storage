<x-layouts.authenticated title="Aging">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Aging / expiry alerts</h2><p class="mt-1 text-sm text-muted">Based on GRN received date and product shelf-life.</p></div>
    @if ($alerts->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-2 font-semibold">Expiring within 14 days</h3>
            <ul class="text-sm space-y-1">@foreach($alerts as $lot)<li>{{ $lot->lot_number }} · {{ $lot->commodity?->name }} · expires {{ $lot->expires_on?->toDateString() }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Lot</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Received</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Qty</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($lots as $lot)
                    <tr><td class="px-4 py-3">{{ $lot->lot_number }}</td><td class="px-4 py-3">{{ $lot->commodity?->name }}</td><td class="px-4 py-3">{{ $lot->received_at }}</td><td class="px-4 py-3">{{ $lot->expires_on ?? '—' }}</td><td class="px-4 py-3">{{ $lot->quantity }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
