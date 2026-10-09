<x-layouts.authenticated title="Incident tickets">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Incident tickets</h2>
        <p class="mt-1 text-sm text-muted">Tickets opened from monitoring alarms (no separate ticket platform).</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Alarm</th><th class="px-4 py-3">Opened</th><th class="px-4 py-3">Notes</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($incidents as $incident)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('monitoring.alarms.show', $incident) }}">{{ $incident->incident_number }}</a></td>
                        <td class="px-4 py-3">{{ $incident->chamber?->code }}</td>
                        <td class="px-4 py-3">#{{ $incident->id }} · {{ $incident->status }}</td>
                        <td class="px-4 py-3">{{ $incident->incident_created_at }}</td>
                        <td class="px-4 py-3">{{ $incident->incident_notes }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No incident tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
