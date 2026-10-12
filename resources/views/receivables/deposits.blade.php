<x-layouts.authenticated title="Deposits">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Advances &amp; security deposits</h2>
            <p class="mt-1 text-sm text-muted">Tracked separately from invoice payments. Refunds require Manager approval and Super Admin execution.</p>
        </div>
        <a class="btn-ghost" href="{{ route('payments.index') }}">Payments</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm text-left">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Customer</th><th class="px-4 py-3">Kind</th><th class="px-4 py-3">Original</th>
                <th class="px-4 py-3">Used</th><th class="px-4 py-3">Refunded</th><th class="px-4 py-3">Remaining</th><th class="px-4 py-3">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($deposits as $deposit)
                    <tr>
                        <td class="px-4 py-3">{{ $deposit->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $deposit->kind }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $deposit->original_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $deposit->used_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $deposit->refunded_amount, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($deposit->remainingBalance(), 2) }}</td>
                        <td class="px-4 py-3 space-y-2">
                            @if ($desk->canRequestRefund($user) && $deposit->remainingBalance() > 0)
                                <form method="POST" action="{{ route('payments.deposits.refund', $deposit) }}" class="flex flex-wrap gap-1">
                                    @csrf
                                    <input name="amount" type="number" step="0.01" max="{{ $deposit->remainingBalance() }}" required placeholder="Amt" class="field w-24">
                                    <input name="reason" required placeholder="Reason" class="field w-40">
                                    <button class="btn-ghost" type="submit">Request refund</button>
                                </form>
                            @endif
                            @if ($desk->canAllocate($user) && $deposit->remainingBalance() > 0)
                                <form method="POST" action="{{ route('payments.deposits.use', $deposit) }}" class="flex flex-wrap gap-1">
                                    @csrf
                                    <select name="storage_invoice_id" class="field w-40" required>
                                        @foreach ($invoices->where('customer_id', $deposit->customer_id) as $inv)
                                            <option value="{{ $inv->id }}">{{ $inv->number ?? '#'.$inv->id }}</option>
                                        @endforeach
                                    </select>
                                    <input name="amount" type="number" step="0.01" required class="field w-24">
                                    <button class="btn-ghost" type="submit">Apply</button>
                                </form>
                            @endif
                            @foreach ($deposit->refundRequests->where('status', 'pending') as $req)
                                @if ($desk->canApproveRefund($user))
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('payments.refunds.decide', $req) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve #{{ $req->id }}</button></form>
                                        <form method="POST" action="{{ route('payments.refunds.decide', $req) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                                    </div>
                                @endif
                            @endforeach
                            @foreach ($deposit->refundRequests->where('status', 'approved') as $req)
                                @if ($desk->canExecuteRefund($user))
                                    <form method="POST" action="{{ route('payments.refunds.execute', $req) }}">@csrf<button class="btn-primary" type="submit">Execute refund #{{ $req->id }}</button></form>
                                @endif
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="7">No deposits. Record via payment kind Advance / Security.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
