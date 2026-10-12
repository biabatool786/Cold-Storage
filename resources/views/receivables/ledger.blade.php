<x-layouts.authenticated title="Customer ledger">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Customer ledger / statement</h2>
            <p class="mt-1 text-sm text-muted">Built from posted invoices, effective payments, credits and write-offs.</p>
        </div>
        <a class="btn-ghost" href="{{ route('payments.index') }}">Payments</a>
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="customer_id" class="field" required>
            <option value="">Select customer</option>
            @foreach ($customers as $c)
                <option value="{{ $c->id }}" @selected(($customer->id ?? null) == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <button class="btn-primary" type="submit">View</button>
        @if ($customer && $desk->canPrint($user))
            <a class="btn-ghost" href="{{ route('payments.ledger.print', $customer) }}" target="_blank">Print / export</a>
        @endif
    </form>
    @if ($customer)
        <div class="mb-4 flex flex-wrap gap-2">
            @if ($desk->canRemind($user))
                <form method="POST" action="{{ route('payments.remind', $customer) }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="message" value="Please settle outstanding dues.">
                    <button class="btn-ghost" type="submit">Send reminder</button>
                </form>
            @endif
            @if ($desk->canHoldForDues($user))
                @if ($customer->status === 'hold')
                    <form method="POST" action="{{ route('payments.hold.release', $customer) }}" class="flex gap-2">
                        @csrf
                        <input name="reason" required value="Released after review" class="field">
                        <button class="btn-ghost" type="submit">Remove hold</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('payments.hold', $customer) }}" class="flex gap-2">
                        @csrf
                        <input name="reason" required value="Outstanding dues hold" class="field">
                        <button class="btn-ghost" type="submit">Place dues hold</button>
                    </form>
                @endif
            @endif
        </div>
    @endif
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($statement)
        <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
            <table class="min-w-full text-sm text-left">
                <thead class="text-xs uppercase text-muted"><tr>
                    <th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Debit</th><th class="px-4 py-3">Credit</th><th class="px-4 py-3">Balance</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($statement['lines'] as $line)
                        <tr>
                            <td class="px-4 py-3">{{ $line['date'] }}</td>
                            <td class="px-4 py-3">{{ $line['type'] }}</td>
                            <td class="px-4 py-3">{{ $line['reference'] }}</td>
                            <td class="px-4 py-3">{{ number_format($line['debit'], 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($line['credit'], 2) }}</td>
                            <td class="px-4 py-3">{{ number_format($line['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm">Outstanding balance: <strong>{{ number_format($statement['closing'], 2) }}</strong></p>
    @endif
</x-layouts.authenticated>
