<x-layouts.authenticated title="Transfer-out">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Branch transfer-out</h2><p class="mt-1 text-sm text-muted">Manager approval required. Uses inventory dispatch movements.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canTransferOut(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('dispatch.transfer-outs.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }} · avail {{ $lot->availableQuantity() }}</option>@endforeach</select>
            <select name="to_branch_id" class="field" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity" class="field">
            <input name="reason" placeholder="Reason" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Request transfer-out</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($rows as $row)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm flex flex-wrap items-center justify-between gap-2">
                <div><p class="font-semibold">{{ $row->lot?->lot_number }} → {{ $row->toBranch?->name }} · {{ $row->quantity }} · {{ $row->status }}</p></div>
                @if ($row->status === 'pending' && $desk->canApproveTransferOut(auth()->user()))
                    <form method="POST" action="{{ route('dispatch.transfer-outs.approve', $row) }}">@csrf<button class="btn-ghost" type="submit">Approve</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
