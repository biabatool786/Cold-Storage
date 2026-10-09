<x-layouts.authenticated title="Transfers">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Internal transfers</h2><p class="mt-1 text-sm text-muted">Request, approve, then scan source and destination slots.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    @if ($desk->canCreateTransfer(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('inventory.transfers.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }} · {{ $lot->slot?->code }} · avail {{ $lot->availableQuantity() }}</option>@endforeach</select>
            <select name="to_slot_id" class="field" required>@foreach($slots as $slot)<option value="{{ $slot->id }}">{{ $slot->code }}</option>@endforeach</select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity" class="field">
            <input name="reason" placeholder="Reason" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Request transfer</button></div>
        </form>
    @endif
    <div class="space-y-4">
        @forelse ($transfers as $transfer)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <p class="font-semibold">{{ $transfer->lot?->lot_number }} · {{ $transfer->quantity }} · {{ $transfer->status }}</p>
                <p class="text-muted">{{ $transfer->fromSlot?->code }} → {{ $transfer->toSlot?->code }}</p>
                @if ($transfer->status === 'pending' && $desk->canApproveTransfer(auth()->user()))
                    <form method="POST" action="{{ route('inventory.transfers.approve', $transfer) }}" class="mt-2">@csrf<button class="btn-ghost" type="submit">Approve</button></form>
                @endif
                @if ($transfer->status === 'approved' && $desk->canExecuteTransfer(auth()->user()))
                    <form method="POST" action="{{ route('inventory.transfers.execute', $transfer) }}" class="mt-2 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="from_slot_id" value="{{ $transfer->from_slot_id }}">
                        <input type="hidden" name="to_slot_id" value="{{ $transfer->to_slot_id }}">
                        <span class="text-muted">Scan confirm: {{ $transfer->fromSlot?->code }} → {{ $transfer->toSlot?->code }}</span>
                        <button class="btn-primary" type="submit">Execute transfer</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-line bg-panel px-4 py-6 text-muted">No transfers.</p>
        @endforelse
    </div>
</x-layouts.authenticated>
