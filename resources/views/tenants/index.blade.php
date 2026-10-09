<x-layouts.authenticated title="Tenants">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Tenants</h2>
            <p class="mt-1 text-sm text-muted">Cold storages on the platform.</p>
        </div>
        <a href="{{ route('tenants.create') }}" class="btn-primary">New cold storage</a>
    </div>
    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Users</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($tenants as $tenant)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a class="text-accent" href="{{ route('tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                        <td class="px-4 py-3 text-muted">{{ $tenant->email }}</td>
                        <td class="px-4 py-3">{{ $tenant->users_count }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$tenant->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-muted">No cold storages yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tenants->links() }}</div>
</x-layouts.authenticated>
