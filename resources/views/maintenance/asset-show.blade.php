<x-layouts.authenticated title="{{ $asset->displayName() }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $asset->displayName() }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $asset->asset_code }} · {{ $asset->operational_status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('assets.index') }}">Back</a>
            @if ($desk->canManageAssets($user))
                <a class="btn-ghost" href="{{ route('assets.edit', $asset) }}">Edit</a>
            @endif
            @if ($desk->canPrintQr($user))
                <a class="btn-primary" target="_blank" href="{{ route('assets.qr', $asset) }}">Print QR</a>
            @endif
            @if ($desk->canPrintHistory($user))
                <a class="btn-ghost" target="_blank" href="{{ route('assets.history.print', $asset) }}">Print history</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3 text-sm">
        <div><span class="text-muted">Cost</span><div class="font-medium">{{ $asset->purchase_cost !== null ? number_format((float) $asset->purchase_cost, 2) : '—' }}</div></div>
        @unless ($costOnly)
            <div><span class="text-muted">Serial</span><div class="font-medium">{{ $asset->serial_number ?: '—' }}</div></div>
            <div><span class="text-muted">Location</span><div class="font-medium">{{ $asset->location ?: '—' }}</div></div>
        @endunless
    </div>
    @if ($history)
        <div class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4 text-sm">
            <div><span class="text-muted">Downtime (h)</span><div class="font-medium">{{ number_format($history['downtime_hours'], 2) }}</div></div>
            <div><span class="text-muted">Tickets</span><div class="font-medium">{{ $history['tickets'] }}</div></div>
            <div><span class="text-muted">Work orders</span><div class="font-medium">{{ $history['work_orders'] }}</div></div>
            <div><span class="text-muted">Actual cost</span><div class="font-medium">{{ number_format($history['actual_cost'], 2) }}</div></div>
        </div>
    @endif
</x-layouts.authenticated>
