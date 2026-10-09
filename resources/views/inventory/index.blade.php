<x-layouts.authenticated title="Stock overview">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Stock overview</h2>
        <p class="mt-1 text-sm text-muted">Stored lots from completed put-away only.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="customer_id" class="field w-auto"><option value="">Customer</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(($filters['customer_id'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="commodity_id" class="field w-auto"><option value="">Product</option>@foreach($commodities as $p)<option value="{{ $p->id }}" @selected(($filters['commodity_id'] ?? '') == $p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="chamber_id" class="field w-auto"><option value="">Chamber</option>@foreach($chambers as $ch)<option value="{{ $ch->id }}" @selected(($filters['chamber_id'] ?? '') == $ch->id)>{{ $ch->code }}</option>@endforeach</select>
        <button class="btn-ghost" type="submit">Filter</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Lot</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Location</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Avail</th>
                <th class="px-4 py-3">Held</th><th class="px-4 py-3">Quarantine</th><th class="px-4 py-3">Dispatched</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($lots as $lot)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('inventory.lots.show', $lot) }}">{{ $lot->lot_number }}</a></td>
                        <td class="px-4 py-3">{{ $lot->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $lot->commodity?->name }}@if($lot->variety) / {{ $lot->variety->name }}@endif</td>
                        <td class="px-4 py-3">{{ $lot->chamber?->code }} · {{ $lot->slot?->code ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $lot->quantity }}</td>
                        <td class="px-4 py-3">{{ $lot->availableQuantity() }}</td>
                        <td class="px-4 py-3">{{ $lot->held_qty }}</td>
                        <td class="px-4 py-3">{{ $lot->quarantined_qty }}</td>
                        <td class="px-4 py-3">{{ $lot->dispatched_qty }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="9">No stored stock.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
