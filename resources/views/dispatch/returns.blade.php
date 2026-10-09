<x-layouts.authenticated title="Returns">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Returned / rejected goods</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canHandleReturns(auth()->user()) && $orders->isNotEmpty())
        <form method="POST" action="{{ route('dispatch.returns.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="delivery_order_id" class="field" required>@foreach($orders as $order)<option value="{{ $order->id }}">{{ $order->number }} · {{ $order->status }}</option>@endforeach</select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity" class="field">
            <input name="reason" required placeholder="Reason" class="field">
            <input name="condition" placeholder="Condition" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record return</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($returns as $row)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm flex flex-wrap items-center justify-between gap-2">
                <div><p class="font-semibold">{{ $row->order?->number }} · {{ $row->quantity }} · {{ $row->status }}</p><p class="text-muted">{{ $row->reason }} · {{ $row->condition }}</p></div>
                @if ($row->status === 'pending' && $desk->canApproveReturn(auth()->user()))
                    <form method="POST" action="{{ route('dispatch.returns.approve', $row) }}">@csrf<button class="btn-ghost" type="submit">Approve</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
