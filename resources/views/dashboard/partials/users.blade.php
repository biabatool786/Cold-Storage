<div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="text-xs uppercase tracking-wide text-muted">
            <tr>
                <th class="py-2 pr-4 font-medium">Name</th>
                <th class="py-2 pr-4 font-medium">Email</th>
                <th class="py-2 pr-4 font-medium">Tenant</th>
                <th class="py-2 font-medium">Role</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($users as $account)
                <tr>
                    <td class="py-3 pr-4 font-medium">{{ $account->name }}</td>
                    <td class="py-3 pr-4 text-muted">{{ $account->email }}</td>
                    <td class="py-3 pr-4">{{ $account->tenant?->name ?? 'Platform' }}</td>
                    <td class="py-3">{{ $account->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-8 text-muted">No other accounts in this scope.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
