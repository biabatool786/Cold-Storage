<x-layouts.authenticated title="Accrued charges">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Accrued charges per lot</h2>
        <p class="mt-1 text-sm text-muted">Calculated from rate cards, storage days, free days and grace periods.</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Lot</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Days</th>
                <th class="px-4 py-3">Billable</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Invoiced</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['lot']->lot_number }}</td>
                        <td class="px-4 py-3">{{ $row['lot']->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $row['storage_days'] }}</td>
                        <td class="px-4 py-3">{{ $row['billable_days'] }}</td>
                        <td class="px-4 py-3">{{ number_format($row['total'], 2) }}</td>
                        <td class="px-4 py-3">{{ $row['already_invoiced'] ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No lots in scope.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
