<x-layouts.authenticated title="Delivery orders">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Delivery order register</h2>
            <p class="mt-1 text-sm text-muted">Outbound dispatch from stored inventory lots.</p>
        </div>
        @if ($desk->canCreate(auth()->user()))
            <a class="btn-primary" href="{{ route('deliveries.create') }}">Create DO</a>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">DO</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Qty</th>
                <th class="px-4 py-3">Status</th>@if($duesDetail)<th class="px-4 py-3">Dues</th>@else<th class="px-4 py-3">Dues flag</th>@endif
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('deliveries.show', $order) }}">{{ $order->number ?? '#'.$order->id }}</a></td>
                        <td class="px-4 py-3">{{ $order->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $order->quantity }}</td>
                        <td class="px-4 py-3">{{ $order->status }}</td>
                        <td class="px-4 py-3">@if($duesDetail){{ $order->dues_amount }}@else{{ $order->has_dues_flag ? 'Outstanding' : 'Clear' }}@endif</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No delivery orders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
