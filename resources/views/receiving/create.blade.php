<x-layouts.authenticated title="Create GRN">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Create GRN draft</h2>
        <p class="mt-1 text-sm text-muted">Link gate entry, booking, and weighment. Weights come from the weighbridge ticket.</p>
    </div>
    @if ($errors->any())
        <ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    @if ($entries->isEmpty())
        <p class="rounded-xl border border-line bg-panel px-4 py-6 text-sm text-muted">No open inbound gate entries. Record gate entry first.</p>
    @else
        <form method="POST" action="{{ route('grns.store', $entries->first()) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm md:col-span-2">Gate entry
                <select class="field mt-1" onchange="this.form.action = this.value" required>
                    @foreach ($entries as $entry)
                        <option value="{{ route('grns.store', $entry) }}">{{ $entry->vehicle_no }} · #{{ $entry->id }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Customer
                <select name="customer_id" class="field mt-1" required>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Product
                <select name="commodity_id" class="field mt-1" required>
                    @foreach ($commodities as $commodity)
                        <option value="{{ $commodity->id }}">{{ $commodity->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Booking
                <select name="booking_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($bookings as $booking)
                        <option value="{{ $booking->id }}">#{{ $booking->id }} · {{ $booking->customer?->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Weighment
                <select name="weighment_id" class="field mt-1">
                    <option value="">Latest for gate</option>
                    @foreach ($weighments as $ticket)
                        <option value="{{ $ticket->id }}">{{ $ticket->number ?? '#'.$ticket->id }} · net {{ $ticket->net_weight ?? '—' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Received qty<input name="quantity" type="number" min="0.001" step="0.001" required class="field mt-1"></label>
            <label class="text-sm">Grade<input name="grade" class="field mt-1"></label>
            <label class="text-sm">Temperature<input name="temperature" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm">Loader count<input name="loader_count" type="number" min="0" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Condition<textarea name="condition_notes" class="field mt-1" rows="2"></textarea></label>
            <label class="text-sm md:col-span-2">Unloading notes<textarea name="unloading_notes" class="field mt-1" rows="2"></textarea></label>
            <label class="text-sm">Shortage qty<input name="shortage_qty" type="number" min="0" step="0.001" class="field mt-1"></label>
            <label class="text-sm">Damage qty<input name="damage_qty" type="number" min="0" step="0.001" class="field mt-1"></label>
            <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" name="submit" value="1"> Submit for approval</label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Save GRN draft</button></div>
        </form>
    @endif
</x-layouts.authenticated>
