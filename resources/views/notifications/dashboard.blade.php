<x-layouts.authenticated title="Notifications">
    <h2 class="text-2xl font-semibold tracking-tight mb-1">Notification dashboard</h2>
    <p class="text-sm text-muted mb-6">Live message and alert summary for your tenant.</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="rounded-2xl border border-line bg-panel p-4"><div class="text-xs uppercase text-muted">Total</div><div class="text-2xl font-semibold">{{ $stats['total'] }}</div></div>
        <div class="rounded-2xl border border-line bg-panel p-4"><div class="text-xs uppercase text-muted">Sent</div><div class="text-2xl font-semibold">{{ $stats['sent'] }}</div></div>
        <div class="rounded-2xl border border-line bg-panel p-4"><div class="text-xs uppercase text-muted">Failed</div><div class="text-2xl font-semibold">{{ $stats['failed'] }}</div></div>
        <div class="rounded-2xl border border-line bg-panel p-4"><div class="text-xs uppercase text-muted">Pending</div><div class="text-2xl font-semibold">{{ $stats['pending'] }}</div></div>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-medium mb-3">Gateway status</h3>
            <ul class="space-y-2 text-sm">
                @forelse($stats['gateways'] as $g)
                    <li>{{ $g['channel'] }} — {{ $g['status'] }} @if(!empty($g['is_enabled']))(enabled)@endif</li>
                @empty
                    <li class="text-muted">No gateways configured.</li>
                @endforelse
            </ul>
        </div>
        <div class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-medium mb-3">Recent alerts</h3>
            <ul class="space-y-2 text-sm">
                @forelse($stats['recent_alerts'] as $a)
                    <li>
                        <strong>{{ $a->title }}</strong>
                        <span class="text-muted">{{ $a->category }}</span>
                        @unless($a->read_at)
                            <form method="POST" action="{{ route('notifications.alerts.read', $a) }}" class="inline">@csrf<button class="underline text-xs" type="submit">Mark read</button></form>
                        @endunless
                        <div class="text-muted">{{ Str::limit($a->body, 100) }}</div>
                    </li>
                @empty
                    <li class="text-muted">No alerts.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.authenticated>
