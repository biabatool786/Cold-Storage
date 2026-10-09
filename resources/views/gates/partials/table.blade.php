<div class="overflow-x-auto rounded-2xl border border-line bg-panel">
    <table class="min-w-full text-left text-sm">
        <thead class="text-xs uppercase tracking-wide text-muted"><tr>
            <th class="px-4 py-3">Vehicle</th><th class="px-4 py-3">Driver</th><th class="px-4 py-3">Purpose</th><th class="px-4 py-3">Status</th>
        </tr></thead>
        <tbody class="divide-y divide-line">
            @forelse ($entries as $entry)
                <tr>
                    <td class="px-4 py-3"><a class="font-medium" href="{{ route('gates.show', $entry) }}">{{ $entry->vehicle_no }}</a></td>
                    <td class="px-4 py-3">{{ $entry->driver_name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $entry->purpose }}</td>
                    <td class="px-4 py-3">{{ $entry->status }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="4">No gate entries.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
