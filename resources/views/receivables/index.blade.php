<x-layouts.authenticated title="Payments & Receivables">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Payments &amp; Receivables</h2>
            <p class="mt-1 text-sm text-muted">Cash, bank, cheque and wallet receipts with allocations and AR tools.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('payments.deposits') }}">Deposits</a>
            <a class="btn-ghost" href="{{ route('payments.ledger') }}">Customer ledger</a>
            <a class="btn-ghost" href="{{ route('payments.aging') }}">Aging</a>
            <a class="btn-ghost" href="{{ route('payments.write-offs') }}">Bad debt</a>
            <a class="btn-ghost" href="{{ route('payments.loans') }}">Stock loans</a>
            @if ($desk->canRecordPayment(auth()->user()))
                <a class="btn-primary" href="{{ route('payments.create') }}">Record payment</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Id</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Method</th>
                <th class="px-4 py-3">Amount</th><th class="px-4 py-3">Unapplied</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('payments.show', $payment) }}">#{{ $payment->id }}</a></td>
                        <td class="px-4 py-3">{{ $payment->customer?->name ?? $payment->invoice?->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $payment->method }}@if($payment->cheque_status) / {{ $payment->cheque_status }}@endif</td>
                        <td class="px-4 py-3">{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $payment->unapplied_amount, 2) }}</td>
                        <td class="px-4 py-3">
                            {{ $payment->status }}
                            @if ($payment->isReversed()) / reversed @endif
                            @if ($payment->reverse_status === 'pending') / reverse pending @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No payments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
