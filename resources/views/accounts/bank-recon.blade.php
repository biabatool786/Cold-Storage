<x-layouts.authenticated title="Bank reconciliation">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Bank reconciliation</h2>
            <p class="mt-1 text-sm text-muted">Match statement lines to recorded payments. Unmatched lines block period close.</p>
        </div>
        <a class="btn-ghost" href="{{ route('expenses.index') }}">Expenses</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canExecuteAccounts(auth()->user()) && $branch)
        <form method="POST" action="{{ route('accounts.bank.lines.store', $branch) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <input name="statement_date" type="date" required value="{{ now()->toDateString() }}" class="field">
            <input name="description" required placeholder="Description" class="field">
            <input name="amount" type="number" step="0.01" required class="field">
            <input name="reference" placeholder="Ref" class="field">
            <div class="md:col-span-4"><button class="btn-primary" type="submit">Add statement line</button></div>
        </form>
        <form method="POST" action="{{ route('accounts.bank.complete', $branch) }}" class="mb-4 flex flex-wrap gap-2">
            @csrf
            <input name="period_start" type="date" required value="{{ now()->startOfMonth()->toDateString() }}" class="field">
            <input name="period_end" type="date" required value="{{ now()->toDateString() }}" class="field">
            <button class="btn-ghost" type="submit">Complete reconciliation</button>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Match</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($lines as $line)
                <tr>
                    <td class="px-4 py-3">{{ optional($line->statement_date)->toDateString() }}</td>
                    <td class="px-4 py-3">{{ $line->description }}</td>
                    <td class="px-4 py-3">{{ number_format((float) $line->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ $line->match_status }}</td>
                    <td class="px-4 py-3">
                        @if ($desk->canExecuteAccounts(auth()->user()) && $line->match_status === 'unmatched')
                            <form method="POST" action="{{ route('accounts.bank.match', $line) }}" class="flex gap-1">
                                @csrf
                                <select name="matched_type" class="field w-40"><option value="vendor_payment">Vendor pay</option><option value="storage_payment">Customer pay</option><option value="operating_expense">Expense</option></select>
                                <input name="matched_id" type="number" required placeholder="ID" class="field w-24">
                                <button class="btn-ghost" type="submit">Match</button>
                            </form>
                        @else
                            {{ $line->matched_type }} #{{ $line->matched_id }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="5">No statement lines.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
