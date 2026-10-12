<x-layouts.authenticated title="Period close">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Period / month closing</h2>
        <p class="mt-1 text-sm text-muted">Accountant requests close; Super Admin approves. Reopen requires Super Admin and a reason.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canExecuteAccounts($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('accounts.periods.close', $branches->first()) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="period_start" type="date" required value="{{ now()->startOfMonth()->toDateString() }}" class="field">
            <input name="period_end" type="date" required value="{{ now()->endOfMonth()->toDateString() }}" class="field">
            <button class="btn-primary" type="submit">Request close</button>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Period</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($periods as $period)
                <tr>
                    <td class="px-4 py-3">{{ optional($period->period_start)->toDateString() }} – {{ optional($period->period_end)->toDateString() }}</td>
                    <td class="px-4 py-3">{{ $period->status }}</td>
                    <td class="px-4 py-3 space-x-1">
                        @if ($period->status === 'close_pending' && $desk->canApproveAccounts($user))
                            <form class="inline" method="POST" action="{{ route('accounts.periods.decide', $period) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve close</button></form>
                            <form class="inline" method="POST" action="{{ route('accounts.periods.decide', $period) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                        @endif
                        @if ($period->status === 'closed' && $desk->canApproveAccounts($user))
                            <form class="inline flex gap-1" method="POST" action="{{ route('accounts.periods.reopen', $period) }}">
                                @csrf
                                <input name="reason" required placeholder="Reopen reason" class="field">
                                <button class="btn-ghost" type="submit">Reopen</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="3">No periods.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
