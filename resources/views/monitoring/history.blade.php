<x-layouts.authenticated title="Monitoring history">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Historical graphs / logs</h2>
            <p class="mt-1 text-sm text-muted">Immutable sensor readings. Correct via annotation, never delete.</p>
        </div>
        <div class="flex gap-2">
            @if ($desk->canViewDashboard(auth()->user()))
                <a class="btn-ghost" href="{{ route('monitoring.index') }}">Dashboard</a>
            @endif
            @if ($desk->canPrint(auth()->user()))
                <a class="btn-ghost" href="{{ route('monitoring.excursions.print') }}" target="_blank">Excursion report</a>
            @endif
        </div>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">When</th><th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Temp</th>
                <th class="px-4 py-3">Humidity</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Sensor</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($readings as $reading)
                    <tr>
                        <td class="px-4 py-3">{{ $reading->created_at }}</td>
                        <td class="px-4 py-3">{{ $reading->chamber?->code }}</td>
                        <td class="px-4 py-3">{{ $reading->temperature }}</td>
                        <td class="px-4 py-3">{{ $reading->humidity ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $reading->source }}</td>
                        <td class="px-4 py-3">{{ $reading->sensor?->code ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No readings yet. Live device feeds are unavailable until integrated; use manual logs where authorized.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
