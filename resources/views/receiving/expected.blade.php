<x-layouts.authenticated title="Expected arrivals">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Expected arrivals</h2>
        <p class="mt-1 text-sm text-muted">Approved bookings and open inbound gate entries ready for receiving.</p>
    </div>
    <div class="mb-8 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Booking</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3">#{{ $booking->id }}</td>
                        <td class="px-4 py-3">{{ $booking->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->commodity?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->quantity }}</td>
                        <td class="px-4 py-3">{{ $booking->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No expected bookings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <h3 class="mb-3 text-lg font-semibold">Open inbound gates</h3>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Vehicle</th><th class="px-4 py-3">Entered</th><th class="px-4 py-3">Weighments</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3">{{ $entry->vehicle_no }}</td>
                        <td class="px-4 py-3">{{ $entry->entered_at }}</td>
                        <td class="px-4 py-3">{{ $entry->weighments->count() }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($desk->canCreate(auth()->user()))
                                <a class="btn-ghost" href="{{ route('grns.create') }}">Create GRN</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No open inbound entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
