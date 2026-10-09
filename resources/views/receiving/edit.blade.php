<x-layouts.authenticated title="Edit GRN">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Edit {{ $receipt->number ?? 'GRN #'.$receipt->id }}</h2>
        <p class="mt-1 text-sm text-muted">Status: {{ $receipt->status }}</p>
    </div>
    @if ($errors->any())
        <ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <form method="POST" action="{{ route('grns.update', $receipt) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-sm">Quantity<input name="quantity" type="number" min="0.001" step="0.001" value="{{ old('quantity', $receipt->quantity) }}" class="field mt-1"></label>
        <label class="text-sm">Grade<input name="grade" value="{{ old('grade', $receipt->grade) }}" class="field mt-1"></label>
        <label class="text-sm">Temperature<input name="temperature" type="number" step="0.01" value="{{ old('temperature', $receipt->temperature) }}" class="field mt-1"></label>
        <label class="text-sm">Loader count<input name="loader_count" type="number" min="0" value="{{ old('loader_count', $receipt->loader_count) }}" class="field mt-1"></label>
        <label class="text-sm">Shortage<input name="shortage_qty" type="number" min="0" step="0.001" value="{{ old('shortage_qty', $receipt->shortage_qty) }}" class="field mt-1"></label>
        <label class="text-sm">Damage<input name="damage_qty" type="number" min="0" step="0.001" value="{{ old('damage_qty', $receipt->damage_qty) }}" class="field mt-1"></label>
        <label class="text-sm">Accepted qty<input name="accepted_qty" type="number" min="0" step="0.001" value="{{ old('accepted_qty', $receipt->accepted_qty) }}" class="field mt-1"></label>
        <label class="text-sm">Booking
            <select name="booking_id" class="field mt-1">
                <option value="">—</option>
                @foreach ($bookings as $booking)
                    <option value="{{ $booking->id }}" @selected((int)$receipt->booking_id === (int)$booking->id)>#{{ $booking->id }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm md:col-span-2">Weighment
            <select name="weighment_id" class="field mt-1">
                <option value="">—</option>
                @foreach ($weighments as $ticket)
                    <option value="{{ $ticket->id }}" @selected((int)$receipt->weighment_id === (int)$ticket->id)>{{ $ticket->number ?? '#'.$ticket->id }} · G {{ $ticket->gross_weight }} / T {{ $ticket->tare_weight }} / N {{ $ticket->net_weight }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm md:col-span-2">Condition<textarea name="condition_notes" class="field mt-1" rows="2">{{ old('condition_notes', $receipt->condition_notes) }}</textarea></label>
        <label class="text-sm md:col-span-2">Unloading<textarea name="unloading_notes" class="field mt-1" rows="2">{{ old('unloading_notes', $receipt->unloading_notes) }}</textarea></label>
        <label class="text-sm md:col-span-2">Inspection notes<textarea name="inspection_notes" class="field mt-1" rows="2">{{ old('inspection_notes', $receipt->inspection_notes) }}</textarea></label>
        @if ($receipt->status === 'approved')
            <label class="text-sm md:col-span-2">Edit reason (required)<input name="reason" required class="field mt-1"></label>
        @else
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="submit" value="1"> Submit for approval</label>
        @endif
        <div class="md:col-span-2 flex gap-2">
            <button class="btn-primary" type="submit">Save</button>
            <a class="btn-ghost" href="{{ route('grns.show', $receipt) }}">Cancel</a>
        </div>
    </form>
</x-layouts.authenticated>
