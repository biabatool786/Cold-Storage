<x-layouts.authenticated title="Live monitoring">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Live chamber dashboard</h2>
            <p class="mt-1 text-sm text-muted">Targets, latest manual logs, and sensor availability. Device feeds show offline until integrated.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('monitoring.alarms') }}">Active alarms</a>
            <a class="btn-ghost" href="{{ route('monitoring.history') }}">History</a>
            <a class="btn-ghost" href="{{ route('monitoring.sensors') }}">Sensors</a>
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if ($desk->canApproveConfig(auth()->user()) && $pendingConfigs->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-3 font-semibold">Pending threshold / escalation approval</h3>
            @foreach ($pendingConfigs as $change)
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3 text-sm">
                    <p>{{ $change->chamber?->code }} · {{ $change->kind }} · temp {{ $change->alarm_temp_min }}–{{ $change->alarm_temp_max }} · esc {{ $change->escalation_minutes }}m</p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('monitoring.config.decide', $change) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('monitoring.config.decide', $change) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($rows as $row)
            @php $chamber = $row['chamber']; @endphp
            <div class="rounded-2xl border border-line bg-panel p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold">{{ $chamber->code }} · {{ $chamber->name }}</h3>
                        <p class="text-xs text-muted">{{ $chamber->branch?->name }}</p>
                    </div>
                    <span class="text-xs">{{ $row['active_alarms'] }} alarm(s)</span>
                </div>
                <dl class="mt-4 space-y-1 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-muted">Latest temp</dt><dd>{{ $row['latest']?->temperature ?? 'Unavailable' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Latest humidity</dt><dd>{{ $row['latest']?->humidity ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Set point</dt><dd>{{ $row['targets']['set_point'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-muted">Alarm band</dt><dd>{{ $row['targets']['alarm_temp_min'] ?? '—' }} to {{ $row['targets']['alarm_temp_max'] ?? '—' }}</dd></div>
                </dl>
                <ul class="mt-3 space-y-1 text-xs">
                    @forelse ($row['sensors'] as $sensor)
                        <li>{{ $sensor['code'] }} · {{ $sensor['live'] ? 'live' : 'offline / unavailable' }}</li>
                    @empty
                        <li class="text-muted">No sensors configured</li>
                    @endforelse
                </ul>
                @if ($desk->canProposeThresholds(auth()->user()))
                    <form method="POST" action="{{ route('monitoring.config.propose', $chamber) }}" class="mt-4 grid gap-2">
                        @csrf
                        <input type="hidden" name="kind" value="thresholds">
                        <input name="alarm_temp_min" type="number" step="0.01" placeholder="Temp min" class="field" value="{{ $chamber->alarm_temp_min }}">
                        <input name="alarm_temp_max" type="number" step="0.01" placeholder="Temp max" class="field" value="{{ $chamber->alarm_temp_max }}">
                        <input name="escalation_minutes" type="number" min="1" placeholder="Escalation minutes" class="field" value="{{ $chamber->escalation_minutes }}">
                        <input name="escalation_role" placeholder="Escalate to role" class="field" value="{{ $chamber->escalation_role }}">
                        <button class="btn-ghost" type="submit">Propose thresholds</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-muted">No chambers in scope.</p>
        @endforelse
    </div>

    @if ($alarms->isNotEmpty())
        <div class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-3 font-semibold">Open alarms</h3>
            @foreach ($alarms as $alarm)
                <p class="mb-2 text-sm"><a href="{{ route('monitoring.alarms.show', $alarm) }}">{{ $alarm->chamber?->code }} · {{ $alarm->status }}</a> — {{ $alarm->message }}</p>
            @endforeach
        </div>
    @endif
</x-layouts.authenticated>
