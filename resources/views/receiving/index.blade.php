<x-layouts.authenticated title="GRN register">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">GRN register</h2>
            <p class="mt-1 text-sm text-muted">Inbound goods receipts linked to gate, booking, and weighment.</p>
        </div>
        @if ($desk->canCreate(auth()->user()))
            <a class="btn-primary" href="{{ route('grns.create') }}">Create GRN</a>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">GRN</th>
                @unless ($limited)<th class="px-4 py-3">Customer</th><th class="px-4 py-3">Product</th>@endunless
                <th class="px-4 py-3">Vehicle</th>
                <th class="px-4 py-3">Qty</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Lot</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($receipts as $receipt)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('grns.show', $receipt) }}">{{ $receipt->number ?? '#'.$receipt->id }}</a></td>
                        @unless ($limited)
                            <td class="px-4 py-3">{{ $receipt->customer?->name }}</td>
                            <td class="px-4 py-3">{{ $receipt->commodity?->name }}</td>
                        @endunless
                        <td class="px-4 py-3">{{ $receipt->gateEntry?->vehicle_no }}</td>
                        <td class="px-4 py-3">{{ $receipt->accepted_qty ?? $receipt->quantity }}</td>
                        <td class="px-4 py-3">{{ $receipt->status }}@if($receipt->outcome) · {{ $receipt->outcome }}@endif</td>
                        <td class="px-4 py-3">{{ $receipt->lot?->lot_number ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="7">No GRNs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
