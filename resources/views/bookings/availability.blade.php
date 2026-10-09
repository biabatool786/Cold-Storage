<x-layouts.authenticated title="Availability">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Space availability</h2>
        <p class="mt-1 text-sm text-muted">Available space is configured chamber capacity minus reserved booking quantity. Inventory occupancy is not included yet.</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Capacity</th><th class="px-4 py-3">Reserved</th><th class="px-4 py-3">Available</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['chamber']->code }} · {{ $row['chamber']->name }}</td>
                        <td class="px-4 py-3">{{ $row['chamber']->capacity ?? '—' }} {{ $row['chamber']->capacity_unit }}</td>
                        <td class="px-4 py-3">{{ $row['reserved'] }}</td>
                        <td class="px-4 py-3">{{ $row['available'] === null ? '—' : $row['available'] }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No chambers configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
