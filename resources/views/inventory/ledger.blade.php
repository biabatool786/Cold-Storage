<x-layouts.authenticated title="Stock ledger">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Stock ledger</h2>
            <p class="mt-1 text-sm text-muted">Auditable movements for receipts, transfers, holds, shrinkage, services and adjustments.</p>
        </div>
        @if ($desk->canViewLedger(auth()->user()))
            <button class="btn-ghost" type="button" onclick="window.print()">Print ledger</button>
        @endif
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">When</th><th class="px-4 py-3">Lot</th><th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Qty</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Reason</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3">{{ $entry->created_at }}</td>
                        <td class="px-4 py-3">{{ $entry->lot?->lot_number ?? '#'.$entry->storage_lot_id }}</td>
                        <td class="px-4 py-3">{{ $entry->type }}</td>
                        <td class="px-4 py-3">{{ $entry->quantity }}</td>
                        <td class="px-4 py-3">{{ $entry->balance_after }}</td>
                        <td class="px-4 py-3">{{ $entry->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No ledger entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
