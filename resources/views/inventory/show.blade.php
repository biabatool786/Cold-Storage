<x-layouts.authenticated title="Lot details">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $lot->lot_number }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $lot->status }}@if($lot->hold_status) · {{ $lot->hold_status }}@endif</p>
        </div>
        @if ($desk->canPrintLabels(auth()->user()))
            <a class="btn-ghost" href="{{ route('inventory.labels', $lot) }}" target="_blank">Print labels</a>
        @endif
    </div>
    <div class="mb-6 grid gap-4 md:grid-cols-2 text-sm">
        <div class="rounded-2xl border border-line bg-panel p-5">
            <p>Customer: {{ $lot->customer?->name }}</p>
            <p>Product: {{ $lot->commodity?->name }}</p>
            <p>Chamber / Slot: {{ $lot->chamber?->code }} / {{ $lot->slot?->code ?? '—' }}</p>
            <p>Received: {{ $lot->received_at ?? '—' }}</p>
            <p>Expires: {{ $lot->expires_on ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-line bg-panel p-5">
            <p>On hand: {{ $lot->quantity }}</p>
            <p>Available: {{ $lot->availableQuantity() }}</p>
            <p>Held: {{ $lot->held_qty }}</p>
            <p>Quarantine: {{ $lot->quarantined_qty }}</p>
            <p>Dispatched: {{ $lot->dispatched_qty }}</p>
            <p>GRN: {{ $lot->receipt?->number ?? '#'.$lot->goods_receipt_id }}</p>
        </div>
    </div>
    <h3 class="mb-3 font-semibold">Recent ledger</h3>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">When</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach ($lot->ledgerEntries as $entry)
                    <tr><td class="px-4 py-3">{{ $entry->type }}</td><td class="px-4 py-3">{{ $entry->quantity }}</td><td class="px-4 py-3">{{ $entry->balance_after }}</td><td class="px-4 py-3">{{ $entry->created_at }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
