<x-layouts.authenticated title="Audit logs">
    <h2 class="text-2xl font-semibold tracking-tight">Audit logs</h2>
    <p class="mt-1 text-sm text-muted">Critical account and cold-storage changes.</p>
    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">When</th>
                    <th class="px-4 py-3 font-medium">Action</th>
                    <th class="px-4 py-3 font-medium">Actor</th>
                    <th class="px-4 py-3 font-medium">Tenant</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 text-muted">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">{{ $log->tenant?->name ?? 'Platform' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-muted">No audit entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.authenticated>
