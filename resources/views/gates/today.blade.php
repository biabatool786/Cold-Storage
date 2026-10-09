<x-layouts.authenticated title="Today's gate">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Today's gate register</h2>
        <p class="mt-1 text-sm text-muted">Vehicles that entered today.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($gate->canCreate(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('gates.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)<option value="{{ route('gates.store', $branch) }}">{{ $branch->name }}</option>@endforeach
            </select>
            <input name="vehicle_no" required placeholder="Vehicle number" class="field">
            <input name="driver_name" placeholder="Driver" class="field">
            <select name="purpose" class="field"><option value="inbound">Inbound</option><option value="outbound">Outbound</option></select>
            <select name="customer_id" class="field"><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
            <select name="booking_id" class="field"><option value="">Booking</option>@foreach ($bookings as $booking)<option value="{{ $booking->id }}">#{{ $booking->id }}</option>@endforeach</select>
            @if ($gate->canRecordSeal(auth()->user()))
                <input name="seal_number" placeholder="Seal number" class="field">
                <input name="vehicle_condition" placeholder="Vehicle condition" class="field">
            @endif
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record entry</button></div>
        </form>
    @endif
    @include('gates.partials.table', ['entries' => $entries])
</x-layouts.authenticated>
