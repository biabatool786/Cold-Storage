<x-layouts.authenticated :title="$page['label']">
    <div class="rounded-2xl border border-line bg-panel p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">{{ $page['label'] }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-muted">{{ $page['description'] }}</p>
            </div>
            @if ($page['area'] === 'monitoring' && auth()->user()->hasPermission(\App\Enums\PermissionName::MonitoringAcknowledge))
                <form method="POST" action="{{ route('workspace.monitoring.acknowledge') }}">
                    @csrf
                    <button class="btn-primary" type="submit">Acknowledge alerts</button>
                </form>
            @endif
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <span class="rounded-full bg-panel-2 px-3 py-1 text-xs text-muted">This tenant only</span>
            <span class="rounded-full bg-stone-100 px-3 py-1 text-xs text-stone-600">No records yet</span>
        </div>
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Record</th>
                        <th class="py-2 pr-4 font-medium">Status</th>
                        <th class="py-2 font-medium">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="3" class="py-8 text-muted">No records yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.authenticated>
