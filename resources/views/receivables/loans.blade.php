<x-layouts.authenticated title="Stock loans">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Loan / advance against stock</h2>
            <p class="mt-1 text-sm text-muted">Linked to customer and lot. No interest or repayment schedule is defined in documentation — outstanding is tracked only.</p>
        </div>
        <a class="btn-ghost" href="{{ route('payments.index') }}">Payments</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCreateLoan($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('payments.loans.store', $branches->first()) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="customer_id" required class="field">
                @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
            <select name="storage_lot_id" required class="field">
                @foreach ($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>@endforeach
            </select>
            <input name="amount" type="number" step="0.01" required class="field" placeholder="Amount">
            <input name="notes" class="field" placeholder="Notes">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record loan (pending approval)</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm text-left">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Customer</th><th class="px-4 py-3">Lot</th><th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Outstanding</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($loans as $loan)
                    <tr>
                        <td class="px-4 py-3">{{ $loan->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $loan->lot?->lot_number }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $loan->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $loan->outstanding_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $loan->status }}</td>
                        <td class="px-4 py-3 space-y-1">
                            @if ($loan->status === 'pending' && $desk->canApproveLoan($user))
                                <div class="flex gap-1">
                                    <form method="POST" action="{{ route('payments.loans.decide', $loan) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                                    <form method="POST" action="{{ route('payments.loans.decide', $loan) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                                </div>
                            @endif
                            @if ($loan->status === 'active' && $desk->canRecordPayment($user))
                                <form method="POST" action="{{ route('payments.loans.repay', $loan) }}" class="flex gap-1">
                                    @csrf
                                    <input name="amount" type="number" step="0.01" required class="field w-28">
                                    <button class="btn-ghost" type="submit">Repay</button>
                                </form>
                            @endif
                            @if ($loan->status === 'active' && (float) $loan->outstanding_amount <= 0.009 && $desk->canRequestLoanClose($user))
                                <form method="POST" action="{{ route('payments.loans.close', $loan) }}">@csrf<button class="btn-ghost" type="submit">Request close</button></form>
                            @endif
                            @if ($loan->status === 'close_pending' && $desk->canApproveLoanClose($user))
                                <div class="flex gap-1">
                                    <form method="POST" action="{{ route('payments.loans.close.decide', $loan) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve close</button></form>
                                    <form method="POST" action="{{ route('payments.loans.close.decide', $loan) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                                </div>
                            @endif
                            @if ($loan->status === 'close_approved' && $desk->canExecuteLoanClose($user))
                                <form method="POST" action="{{ route('payments.loans.close.execute', $loan) }}">@csrf<button class="btn-primary" type="submit">Execute close</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No stock loans.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
