<x-layouts.authenticated title="Expenses">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Expenses</h2>
            <p class="mt-1 text-sm text-muted">Operating expenses and petty-cash requests with approval limits.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('vendors.index') }}">Vendors</a>
            <a class="btn-ghost" href="{{ route('accounts.petty-cash') }}">Petty cash</a>
            <a class="btn-ghost" href="{{ route('accounts.day-book') }}">Day book</a>
            <a class="btn-ghost" href="{{ route('accounts.profit-loss') }}">P&amp;L</a>
            <a class="btn-ghost" href="{{ route('accounts.periods') }}">Period close</a>
            @if ($desk->canCreateExpense(auth()->user()))
                <a class="btn-primary" href="{{ route('expenses.create') }}">New expense</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Date</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Kind</th>
                <th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-4 py-3">{{ optional($expense->expense_date)->toDateString() ?? '—' }}</td>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('expenses.show', $expense) }}">{{ $expense->description }}</a></td>
                        <td class="px-4 py-3">{{ $expense->kind }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $expense->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $expense->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No expenses.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
