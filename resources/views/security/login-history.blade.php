<x-layouts.authenticated title="Login history">
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-semibold tracking-tight">Login history</h2>
        @if ($canExport)<a class="btn-ghost" href="{{ route('login-history.export') }}">Export</a>@endif
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">When</th><th class="px-4 py-3">User</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">IP</th><th class="px-4 py-3">Device</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($events as $event)
                    <tr>
                        <td class="px-4 py-3">{{ $event->created_at }}</td>
                        <td class="px-4 py-3">{{ $event->user?->name ?: $event->email }}</td>
                        <td class="px-4 py-3">{{ $event->action }}</td>
                        <td class="px-4 py-3">{{ $event->ip_address }}</td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($event->user_agent, 80) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-muted">No sign-in events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</x-layouts.authenticated>
