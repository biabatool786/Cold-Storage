<x-layouts.authenticated title="Waiting list">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Waiting list</h2>
        <p class="mt-1 text-sm text-muted">Requests held until configured chamber capacity can take them.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Booking</th><th class="px-4 py-3">Product</th><th class="px-4 py-3">Quantity</th><th class="px-4 py-3">Chamber</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('bookings.show', $booking) }}">#{{ $booking->id }}</a></td>
                        <td class="px-4 py-3">{{ $booking->commodity?->name }}</td>
                        <td class="px-4 py-3">{{ $booking->quantity }}</td>
                        <td class="px-4 py-3">{{ $booking->chamber?->code ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($reservations->canManageQueue(auth()->user()))
                                <form method="POST" action="{{ route('bookings.queue', $booking) }}">@csrf<input type="hidden" name="waiting" value="0"><button class="btn-ghost" type="submit">Release</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No waiting requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
