<x-layouts.authenticated title="Holds">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Holds / quarantine</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canHold(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('inventory.holds.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }} · avail {{ $lot->availableQuantity() }}</option>@endforeach</select>
            <select name="kind" class="field"><option value="hold">Hold</option><option value="quarantine">Quarantine</option></select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity" class="field">
            <input name="reason" required placeholder="Reason" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Place / request hold</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($holds as $hold)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <p class="font-semibold">{{ $hold->lot?->lot_number }} · {{ $hold->kind }} · {{ $hold->quantity }} · {{ $hold->status }}</p>
                <p class="text-muted">{{ $hold->reason }}</p>
                @if ($hold->status === 'requested' && ($desk->canReleaseHold(auth()->user()) || auth()->user()->hasRole(\App\Enums\RoleName::Manager)))
                    <form method="POST" action="{{ route('inventory.holds.activate', $hold) }}" class="mt-2 inline">@csrf<button class="btn-ghost" type="submit">Activate</button></form>
                @endif
                @if ($hold->status === 'active' && $desk->canReleaseHold(auth()->user()))
                    <form method="POST" action="{{ route('inventory.holds.release', $hold) }}" class="mt-2 flex gap-2">@csrf<input name="reason" required placeholder="Release reason" class="field"><button class="btn-primary" type="submit">Release</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
