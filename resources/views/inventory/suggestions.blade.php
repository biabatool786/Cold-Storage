<x-layouts.authenticated title="FIFO / FEFO">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">FIFO / FEFO suggestions</h2>
        <p class="mt-1 text-sm text-muted">Suggestions only — stock is not moved automatically.</p>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="mode" class="field w-auto"><option value="fifo" @selected($mode==='fifo')>FIFO</option><option value="fefo" @selected($mode==='fefo')>FEFO</option></select>
        <select name="commodity_id" class="field w-auto"><option value="">Product</option>@foreach($commodities as $p)<option value="{{ $p->id }}" @selected(request('commodity_id')==$p->id)>{{ $p->name }}</option>@endforeach</select>
        <select name="customer_id" class="field w-auto"><option value="">Customer</option>@foreach($customers as $c)<option value="{{ $c->id }}" @selected(request('customer_id')==$c->id)>{{ $c->name }}</option>@endforeach</select>
        <button class="btn-primary" type="submit">Suggest</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">#</th><th class="px-4 py-3">Lot</th><th class="px-4 py-3">Available</th><th class="px-4 py-3">Received</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Slot</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $i => $lot)
                    <tr>
                        <td class="px-4 py-3">{{ $i+1 }}</td>
                        <td class="px-4 py-3">{{ $lot->lot_number }}</td>
                        <td class="px-4 py-3">{{ $lot->availableQuantity() }}</td>
                        <td class="px-4 py-3">{{ $lot->received_at }}</td>
                        <td class="px-4 py-3">{{ $lot->expires_on ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $lot->slot?->code }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No eligible lots.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
