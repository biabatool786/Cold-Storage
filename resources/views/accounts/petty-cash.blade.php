<x-layouts.authenticated title="Petty cash">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Petty cash book</h2>
            <p class="mt-1 text-sm text-muted">Closing balance: {{ number_format($book['closing'], 2) }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('expenses.index') }}">Expenses</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canPostPettyCash(auth()->user()) && $branch)
        <form method="POST" action="{{ route('accounts.petty-cash.store', $branch) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <select name="kind" class="field"><option value="opening">Opening</option><option value="receipt">Receipt</option><option value="replenishment">Replenishment</option><option value="expense">Expense</option></select>
            <input name="entry_date" type="date" required value="{{ now()->toDateString() }}" class="field">
            <input name="amount" type="number" step="0.01" required class="field">
            <input name="notes" placeholder="Notes" class="field">
            <div class="md:col-span-4"><button class="btn-primary" type="submit">Add entry</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Date</th><th class="px-4 py-3">Kind</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Notes</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($book['entries'] as $entry)
                <tr>
                    <td class="px-4 py-3">{{ optional($entry->entry_date)->toDateString() }}</td>
                    <td class="px-4 py-3">{{ $entry->kind }}</td>
                    <td class="px-4 py-3">{{ number_format((float) $entry->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ $entry->notes }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="4">No petty cash entries.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
