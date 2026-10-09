<x-layouts.authenticated title="Active alarms">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Active alarms</h2>
            <p class="mt-1 text-sm text-muted">Acknowledge, escalate, suppress, or resolve with comments.</p>
        </div>
        <a class="btn-ghost" href="{{ route('monitoring.index') }}">Dashboard</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Message</th><th class="px-4 py-3">Reading</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($alarms as $alarm)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('monitoring.alarms.show', $alarm) }}">{{ $alarm->chamber?->code }}</a></td>
                        <td class="px-4 py-3">{{ $alarm->status }}</td>
                        <td class="px-4 py-3">{{ $alarm->message }}</td>
                        <td class="px-4 py-3">{{ $alarm->reading_temperature }} / {{ $alarm->reading_humidity ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No alarms.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
