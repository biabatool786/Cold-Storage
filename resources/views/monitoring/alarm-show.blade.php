<x-layouts.authenticated title="Alarm detail">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Alarm #{{ $alarm->id }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $alarm->chamber?->code }} · {{ $alarm->status }} · {{ $alarm->message }}</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="mb-6 grid gap-4 md:grid-cols-2 text-sm">
        <div class="rounded-2xl border border-line bg-panel p-5">
            <p>Temp: {{ $alarm->reading_temperature }} · Humidity: {{ $alarm->reading_humidity ?? '—' }}</p>
            <p>Ack: {{ $alarm->acknowledged_at ?? '—' }}</p>
            <p>Resolved: {{ $alarm->resolved_at ?? '—' }} {{ $alarm->resolution_comment }}</p>
            <p>Suppression: {{ $alarm->suppression_status ?? '—' }} until {{ $alarm->suppressed_until ?? '—' }}</p>
            <p>Incident: {{ $alarm->has_incident ? $alarm->incident_number : '—' }}</p>
        </div>
        <div class="space-y-3">
            @if ($desk->canAcknowledge($user) && in_array($alarm->status, ['open', 'escalated'], true))
                <form method="POST" action="{{ route('monitoring.alarms.acknowledge', $alarm) }}">@csrf<button class="btn-primary" type="submit">Acknowledge</button></form>
            @endif
            @if ($desk->canEscalate($user) && ! in_array($alarm->status, ['resolved', 'suppressed'], true))
                <form method="POST" action="{{ route('monitoring.alarms.escalate', $alarm) }}">@csrf<button class="btn-ghost" type="submit">Manual escalation</button></form>
            @endif
            @if ($desk->canResolve($user) && $alarm->status !== 'resolved')
                <form method="POST" action="{{ route('monitoring.alarms.resolve', $alarm) }}" class="flex gap-2">
                    @csrf
                    <input name="comment" required placeholder="Resolution comment" class="field">
                    <button class="btn-primary" type="submit">Resolve</button>
                </form>
            @endif
            @if (($desk->canRequestSuppress($user) || $desk->canApproveSuppress($user)) && $alarm->status !== 'resolved')
                <form method="POST" action="{{ route('monitoring.alarms.suppress', $alarm) }}" class="grid gap-2 rounded-2xl border border-line bg-panel p-4">
                    @csrf
                    <input name="until" type="datetime-local" required class="field">
                    <input name="reason" required placeholder="Maintenance window reason" class="field">
                    <button class="btn-ghost" type="submit">{{ $desk->canApproveSuppress($user) && ! $user->hasRole(\App\Enums\RoleName::Supervisor) ? 'Suppress alarm' : 'Request suppression' }}</button>
                </form>
            @endif
            @if ($desk->canApproveSuppress($user) && $alarm->suppression_status === 'pending')
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('monitoring.alarms.suppress.decide', $alarm) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve suppression</button></form>
                    <form method="POST" action="{{ route('monitoring.alarms.suppress.decide', $alarm) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                </div>
            @endif
            @if ($desk->canCreateIncident($user) && ! $alarm->has_incident)
                <form method="POST" action="{{ route('monitoring.alarms.incident', $alarm) }}" class="flex gap-2">
                    @csrf
                    <input name="notes" placeholder="Incident notes" class="field">
                    <button class="btn-ghost" type="submit">Create incident ticket</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.authenticated>
