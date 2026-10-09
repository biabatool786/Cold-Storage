<x-layouts.authenticated title="Lots">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Receiving lots</h2>
        <p class="mt-1 text-sm text-muted">Lots from approved GRNs. Use Stock overview for full inventory.</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Lot</th>
                @unless ($limited)<th class="px-4 py-3">Customer</th>@endunless
                <th class="px-4 py-3">Qty</th>
                <th class="px-4 py-3">Chamber</th>
                <th class="px-4 py-3">Slot</th>
                <th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($lots as $lot)
                    <tr>
                        <td class="px-4 py-3">{{ $lot->lot_number ?? '#'.$lot->id }}</td>
                        @unless ($limited)<td class="px-4 py-3">{{ $lot->customer?->name }}</td>@endunless
                        <td class="px-4 py-3">{{ $lot->quantity }}</td>
                        <td class="px-4 py-3">{{ $lot->chamber?->code }}</td>
                        <td class="px-4 py-3">{{ $lot->slot?->code ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $lot->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No lots yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
