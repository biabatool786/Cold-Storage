<x-layouts.authenticated title="Downtime & cost">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Downtime &amp; cost history</h2>
            <p class="mt-1 text-sm text-muted">Actuals from recorded breakdowns, work orders, and parts.</p>
        </div>
        <a class="btn-ghost" href="{{ route('assets.index') }}">Assets</a>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Asset</th><th class="px-4 py-3">Downtime (h)</th><th class="px-4 py-3">Tickets</th><th class="px-4 py-3">WOs</th><th class="px-4 py-3">Actual cost</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['asset']->asset_code }} — {{ $row['asset']->displayName() }}</td>
                        <td class="px-4 py-3">{{ number_format($row['history']['downtime_hours'], 2) }}</td>
                        <td class="px-4 py-3">{{ $row['history']['tickets'] }}</td>
                        <td class="px-4 py-3">{{ $row['history']['work_orders'] }}</td>
                        <td class="px-4 py-3">{{ number_format($row['history']['actual_cost'], 2) }}</td>
                        <td class="px-4 py-3">
                            <a class="btn-ghost" href="{{ route('assets.show', $row['asset']) }}">View</a>
                            @if ($desk->canPrintHistory(auth()->user()))
                                <a class="btn-ghost" target="_blank" href="{{ route('assets.history.print', $row['asset']) }}">Print</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No assets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
