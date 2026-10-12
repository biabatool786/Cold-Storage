<x-layouts.authenticated title="Assets">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Asset register</h2>
            <p class="mt-1 text-sm text-muted">Equipment inventory by branch.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('maintenance.schedules') }}">PM schedules</a>
            <a class="btn-ghost" href="{{ route('maintenance.tickets') }}">Breakdowns</a>
            <a class="btn-ghost" href="{{ route('maintenance.work-orders') }}">Work orders</a>
            <a class="btn-ghost" href="{{ route('maintenance.spares') }}">Spares</a>
            @if ($desk->canManageAssets(auth()->user()))
                <a class="btn-primary" href="{{ route('assets.create') }}">Add asset</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Code</th><th class="px-4 py-3">Name</th>
                @unless ($costOnly)<th class="px-4 py-3">Status</th><th class="px-4 py-3">Location</th>@endunless
                <th class="px-4 py-3">Cost</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($assets as $asset)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('assets.show', $asset) }}">{{ $asset->asset_code }}</a></td>
                        <td class="px-4 py-3">{{ $asset->displayName() }}</td>
                        @unless ($costOnly)
                            <td class="px-4 py-3">{{ $asset->operational_status }}</td>
                            <td class="px-4 py-3">{{ $asset->location ?: '—' }}</td>
                        @endunless
                        <td class="px-4 py-3">{{ $asset->purchase_cost !== null ? number_format((float) $asset->purchase_cost, 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No assets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
