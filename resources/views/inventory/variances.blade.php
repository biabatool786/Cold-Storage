<x-layouts.authenticated title="Variances">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Variance &amp; adjustments</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <h3 class="mb-2 font-semibold">Variances</h3>
    <div class="mb-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Count</th><th class="px-4 py-3">Lot</th><th class="px-4 py-3">System</th><th class="px-4 py-3">Counted</th><th class="px-4 py-3">Variance</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($lines as $line)
                    <tr><td class="px-4 py-3">#{{ $line->stock_count_id }}</td><td class="px-4 py-3">{{ $line->lot?->lot_number }}</td><td class="px-4 py-3">{{ $line->system_qty }}</td><td class="px-4 py-3">{{ $line->counted_qty }}</td><td class="px-4 py-3">{{ $line->variance }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No variances.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($desk->canCreateAdjustment(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }} · {{ $lot->quantity }}</option>@endforeach</select>
            <select name="kind" class="field"><option value="adjustment">Adjustment</option><option value="write_off">Write-off</option></select>
            <input name="quantity_after" type="number" min="0" step="0.001" required placeholder="Quantity after" class="field">
            <input name="reason" required placeholder="Reason" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Request adjustment</button></div>
        </form>
    @endif
    <h3 class="mb-2 font-semibold">Adjustment requests</h3>
    <div class="space-y-3">
        @foreach ($adjustments as $adj)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="font-semibold">{{ $adj->lot?->lot_number }} · {{ $adj->kind }} · {{ $adj->status }}</p>
                    <p class="text-muted">{{ $adj->quantity_before }} → {{ $adj->quantity_after }} ({{ $adj->delta }}) · {{ $adj->reason }}</p>
                </div>
                @if ($adj->status === 'pending' && $desk->canApproveAdjustment(auth()->user()))
                    <form method="POST" action="{{ route('inventory.adjustments.approve', $adj) }}">@csrf<button class="btn-ghost" type="submit">Approve</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
