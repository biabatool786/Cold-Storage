<x-layouts.authenticated title="Peak load alerts">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Peak load alerts</h2>
        <p class="mt-1 text-sm text-muted">Record-based alerts from manual meter readings (not real-time monitoring).</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canEditPeakThreshold($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('energy.peak-thresholds.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/energy/peak-thresholds'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Meter (optional)
                <select name="electricity_meter_id" class="field mt-1">
                    <option value="">Branch default</option>
                    @foreach ($meters as $m)<option value="{{ $m->id }}">{{ $m->meter_code }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Threshold (kW / kWh interval)<input name="threshold_kw" type="number" step="0.001" required class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Propose threshold</button></div>
        </form>
    @endif
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Thresholds</h3>
    <div class="mb-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Threshold</th><th class="px-4 py-3">Meter</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($thresholds as $t)
                    <tr>
                        <td class="px-4 py-3">{{ $t->threshold_kw }}</td>
                        <td class="px-4 py-3">{{ $t->meter?->meter_code ?: 'branch' }}</td>
                        <td class="px-4 py-3">{{ $t->status }}</td>
                        <td class="px-4 py-3">
                            @if ($desk->canApprovePeakThreshold($user) && $t->status === 'pending')
                                <form method="POST" action="{{ route('energy.peak-thresholds.decide', $t) }}" class="inline">@csrf
                                    <input type="hidden" name="approve" value="1">
                                    <button class="btn-ghost" type="submit">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('energy.peak-thresholds.decide', $t) }}" class="inline">@csrf
                                    <input type="hidden" name="approve" value="0">
                                    <button class="btn-ghost" type="submit">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No thresholds.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Alerts</h3>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">When</th><th class="px-4 py-3">Measured</th><th class="px-4 py-3">Threshold</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($alerts as $a)
                    <tr>
                        <td class="px-4 py-3">{{ $a->alerted_at }}</td>
                        <td class="px-4 py-3">{{ $a->measured_load }}</td>
                        <td class="px-4 py-3">{{ $a->threshold_kw }}</td>
                        <td class="px-4 py-3">{{ $a->source }}</td>
                        <td class="px-4 py-3">{{ $a->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No alerts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
