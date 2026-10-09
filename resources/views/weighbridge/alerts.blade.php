<x-layouts.authenticated title="Tolerance alerts">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Tolerance alerts</h2>
        <p class="mt-1 text-sm text-muted">Net weight outside the branch tolerance against the booking quantity.</p>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <tbody class="divide-y divide-line">
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('weighments.show', $ticket) }}">{{ $ticket->number }}</a></td>
                        <td class="px-4 py-3">Net {{ $ticket->net_weight }}</td>
                        <td class="px-4 py-3">
                            @if ($desk->canAcknowledge(auth()->user()))
                                <form method="POST" action="{{ route('weighments.acknowledge', $ticket) }}">@csrf<button class="btn-ghost" type="submit">Acknowledge</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted">No open alerts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
