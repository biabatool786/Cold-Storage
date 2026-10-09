<x-layouts.authenticated title="Shrinkage">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Weight-loss / shrinkage</h2><p class="mt-1 text-sm text-muted">Posts an auditable stock movement; balances are never silently changed.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canRecordShrinkage(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('inventory.shrinkage.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }} · avail {{ $lot->availableQuantity() }}</option>@endforeach</select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity lost" class="field">
            <input name="recorded_on" type="date" required value="{{ now()->toDateString() }}" class="field">
            <input name="reason" required placeholder="Reason" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record shrinkage</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Lot</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Reason</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr><td class="px-4 py-3">{{ $row->lot?->lot_number }}</td><td class="px-4 py-3">{{ $row->quantity }}</td><td class="px-4 py-3">{{ $row->recorded_on?->toDateString() }}</td><td class="px-4 py-3">{{ $row->reason }}</td></tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No shrinkage records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
