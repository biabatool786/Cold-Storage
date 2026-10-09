<x-layouts.authenticated title="Create DO">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Create delivery order</h2><p class="mt-1 text-sm text-muted">Stock, dues and credit are checked automatically.</p></div>
    @if ($errors->any())<ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    @if ($lots->isEmpty())
        <p class="rounded-xl border border-line bg-panel px-4 py-6 text-muted">No stored lots available.</p>
    @else
        <form method="POST" action="{{ route('deliveries.store') }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm md:col-span-2">Lot
                <select name="storage_lot_id" class="field mt-1" required>
                    @foreach ($lots as $lot)
                        <option value="{{ $lot->id }}">{{ $lot->lot_number }} · {{ $lot->customer?->name }} · avail {{ $lot->availableQuantity() }} · {{ $lot->slot?->code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Quantity<input name="quantity" type="number" min="0.001" step="0.001" required class="field mt-1"></label>
            <label class="text-sm">Pick mode<select name="pick_mode" class="field mt-1"><option value="fifo">FIFO</option><option value="fefo">FEFO</option></select></label>
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="submit" value="1" checked> Submit for approval</label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create DO</button></div>
        </form>
    @endif
</x-layouts.authenticated>
