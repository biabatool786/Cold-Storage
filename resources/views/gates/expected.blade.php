<x-layouts.authenticated title="Expected vehicles">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Expected vehicles</h2>
        <p class="mt-1 text-sm text-muted">Bookings for today that already have a vehicle number.</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Vehicle</th><th class="px-4 py-3">Driver</th><th class="px-4 py-3">Booking</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3">{{ $booking->vehicle_no }}</td>
                        <td class="px-4 py-3">{{ $booking->driver_name ?? '—' }}</td>
                        <td class="px-4 py-3"><a href="{{ route('bookings.show', $booking) }}">#{{ $booking->id }}</a></td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="3">No expected vehicles.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
