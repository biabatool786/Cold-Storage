<x-layouts.authenticated title="Payment #{{ $payment->id }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Payment #{{ $payment->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $payment->customer?->name }} · {{ $payment->method }} · {{ $payment->status }}@if($payment->cheque_status) · cheque {{ $payment->cheque_status }}@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('payments.index') }}">Back</a>
            @if ($desk->canPrint($user))
                <a class="btn-primary" href="{{ route('payments.receipt', $payment) }}" target="_blank">Receipt</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4 text-sm">
        <div><span class="text-muted">Amount</span><div class="font-medium">{{ number_format((float) $payment->amount, 2) }}</div></div>
        <div><span class="text-muted">Allocated</span><div class="font-medium">{{ number_format((float) $payment->allocated_amount, 2) }}</div></div>
        <div><span class="text-muted">Unapplied</span><div class="font-medium">{{ number_format((float) $payment->unapplied_amount, 2) }}</div></div>
        <div><span class="text-muted">Reference</span><div class="font-medium">{{ $payment->reference ?: '—' }}</div></div>
    </div>

    @if ($desk->canEditSameDay($user) && $payment->isPosted() && ! $payment->isReversed())
        <form method="POST" action="{{ route('payments.edit', $payment) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="reference" value="{{ $payment->reference }}" placeholder="Reference" class="field">
            <input name="notes" value="{{ $payment->notes }}" placeholder="Notes" class="field">
            <button class="btn-ghost" type="submit">Same-day edit</button>
        </form>
    @endif

    @if ($payment->method === 'cheque' && $desk->canManageCheque($user) && ! $payment->isReversed())
        <form method="POST" action="{{ route('payments.cheque', $payment) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <select name="status" class="field">
                <option value="deposited">Deposited</option>
                <option value="cleared">Cleared</option>
                <option value="bounced">Bounced</option>
            </select>
            <button class="btn-ghost" type="submit">Update cheque status</button>
        </form>
    @endif

    @if ($desk->canAllocate($user) && $payment->isFundsEffective() && (float) $payment->unapplied_amount > 0)
        <form method="POST" action="{{ route('payments.allocate', $payment) }}" class="mb-4 space-y-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <p class="text-sm font-medium">Allocate to invoices</p>
            @forelse ($openInvoices as $i => $invoice)
                <div class="flex flex-wrap gap-2">
                    <input type="hidden" name="allocations[{{ $i }}][invoice_id]" value="{{ $invoice->id }}">
                    <span class="text-sm self-center">{{ $invoice->number ?? '#'.$invoice->id }} (out {{ number_format($desk->invoiceOutstanding($invoice), 2) }})</span>
                    <input name="allocations[{{ $i }}][amount]" type="number" step="0.01" min="0" value="0" class="field w-40">
                </div>
            @empty
                <p class="text-sm text-muted">No open invoices for this customer.</p>
            @endforelse
            @if ($openInvoices->count())
                <button class="btn-primary" type="submit">Allocate</button>
            @endif
        </form>
    @endif

    <div class="mb-4 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Amount</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($payment->allocations as $alloc)
                    <tr>
                        <td class="px-4 py-3">{{ $alloc->invoice?->number ?? '#'.$alloc->storage_invoice_id }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $alloc->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-4 text-muted" colspan="2">No allocations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($desk->canRequestReverse($user) && $payment->isPosted() && ! $payment->isReversed() && ! $payment->reverse_status)
        <form method="POST" action="{{ route('payments.reverse', $payment) }}" class="mb-4 flex gap-2">
            @csrf
            <input name="reason" required placeholder="Reversal reason" class="field">
            <button class="btn-ghost" type="submit">Request reverse</button>
        </form>
    @endif
    @if ($payment->reverse_status === 'pending' && $desk->canApproveReverse($user))
        <div class="mb-4 flex gap-2">
            <form method="POST" action="{{ route('payments.reverse.decide', $payment) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve reverse</button></form>
            <form method="POST" action="{{ route('payments.reverse.decide', $payment) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
        </div>
    @endif
    @if ($payment->reverse_status === 'approved' && $desk->canExecuteReverse($user))
        <form method="POST" action="{{ route('payments.reverse.execute', $payment) }}" class="mb-4">@csrf<button class="btn-primary" type="submit">Execute reverse</button></form>
    @endif
</x-layouts.authenticated>
