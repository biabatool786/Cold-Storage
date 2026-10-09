<x-layouts.authenticated title="Excursion reports">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Temperature excursion reports</h2>
            <p class="mt-1 text-sm text-muted">Threshold breaches from available readings.</p>
        </div>
        @if ($desk->canPrint(auth()->user()))
            <a class="btn-primary" href="{{ route('monitoring.excursions.print') }}" target="_blank">Print report</a>
        @endif
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">When</th><th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Reading</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Message</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($alarms as $alarm)
                    <tr>
                        <td class="px-4 py-3">{{ $alarm->created_at }}</td>
                        <td class="px-4 py-3">{{ $alarm->chamber?->code }}</td>
                        <td class="px-4 py-3">{{ $alarm->reading_temperature }}</td>
                        <td class="px-4 py-3">{{ $alarm->status }}</td>
                        <td class="px-4 py-3">{{ $alarm->message }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No excursions recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
