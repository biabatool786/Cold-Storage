<x-layouts.authenticated title="Approval limits">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Approval limits</h2>
        <p class="mt-1 text-sm text-muted">Super Admin sets the manager ceiling. Amounts above the limit stay with Super Admin.</p>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Limit</th>
                    <th class="px-4 py-3 font-medium">Unit</th>
                    <th class="px-4 py-3 font-medium">Manager ceiling</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($limits as $limit)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $limit->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $limit->unit }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('approval-limits.update', $limit) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input name="manager_limit" type="number" min="0" step="0.01" value="{{ $limit->manager_limit }}" class="field w-32">
                                <button class="btn-ghost px-3 py-1.5 text-xs" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
