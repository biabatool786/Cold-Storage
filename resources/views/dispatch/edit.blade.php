<x-layouts.authenticated title="Edit DO">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Edit {{ $order->number ?? 'DO #'.$order->id }}</h2></div>
    @if ($errors->any())<ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
    <form method="POST" action="{{ route('deliveries.update', $order) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-sm">Quantity<input name="quantity" type="number" min="0.001" step="0.001" value="{{ old('quantity', $order->quantity) }}" class="field mt-1"></label>
        <label class="text-sm">Pick mode<select name="pick_mode" class="field mt-1"><option value="fifo" @selected($order->pick_mode==='fifo')>FIFO</option><option value="fefo" @selected($order->pick_mode==='fefo')>FEFO</option></select></label>
        <label class="text-sm">Outgoing temperature<input name="outgoing_temperature" type="number" step="0.01" value="{{ old('outgoing_temperature', $order->outgoing_temperature) }}" class="field mt-1"></label>
        <label class="text-sm md:col-span-2">Loader notes<textarea name="loader_notes" class="field mt-1" rows="2">{{ old('loader_notes', $order->loader_notes) }}</textarea></label>
        @if ($order->isEditableDraft())
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="submit" value="1"> Submit for approval</label>
        @else
            <label class="text-sm md:col-span-2">Edit reason (required)<input name="reason" required class="field mt-1"></label>
        @endif
        <div class="md:col-span-2 flex gap-2"><button class="btn-primary" type="submit">Save</button><a class="btn-ghost" href="{{ route('deliveries.show', $order) }}">Cancel</a></div>
    </form>
</x-layouts.authenticated>
