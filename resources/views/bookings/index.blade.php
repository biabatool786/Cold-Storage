<x-layouts.authenticated :title="$title">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $title }}</h2>
        <p class="mt-1 text-sm text-muted">Customer, product, rate, and requested storage space.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($reservations->canCreate(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('bookings.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div class="md:col-span-2">
                <label class="field-label">Branch</label>
                <select class="field" onchange="this.form.action = this.value">
                    @foreach ($branches as $branch)
                        <option value="{{ route('bookings.store', $branch) }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <select name="customer_id" required class="field"><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
            <select name="commodity_id" required class="field"><option value="">Product</option>@foreach ($commodities as $commodity)<option value="{{ $commodity->id }}">{{ $commodity->name }}</option>@endforeach</select>
            <select name="chamber_id" class="field"><option value="">Chamber</option>@foreach ($chambers as $chamber)<option value="{{ $chamber->id }}">{{ $chamber->code }}</option>@endforeach</select>
            <select name="rate_agreement_id" class="field"><option value="">Rate agreement</option>@foreach ($rates as $rate)<option value="{{ $rate->id }}">{{ $rate->rate }} / {{ $rate->unit }}</option>@endforeach</select>
            <input name="quantity" type="number" min="0.001" step="0.001" required placeholder="Quantity" class="field">
            <input name="rate" type="number" min="0" step="0.01" placeholder="Rate" class="field">
            <input name="starts_on" type="date" class="field">
            <input name="ends_on" type="date" class="field">
            <input name="vehicle_no" placeholder="Vehicle number" class="field">
            <input name="driver_name" placeholder="Driver" class="field">
            <input name="reference" placeholder="Reference" class="field">
            <input name="notes" placeholder="Notes" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create booking</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Booking</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('bookings.show', $booking) }}">#{{ $booking->id }}</a></td>
                        <td class="px-4 py-3">{{ $booking->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->commodity?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->quantity }}</td>
                        <td class="px-4 py-3">{{ $booking->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No bookings yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
