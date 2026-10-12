<x-layouts.authenticated title="Bad debt">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Bad-debt write-offs</h2>
            <p class="mt-1 text-sm text-muted">Accountant requests; Super Admin approves and executes. Posted invoices are not edited.</p>
        </div>
        <a class="btn-ghost" href="{{ route('payments.index') }}">Payments</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canRequestWriteOff($user))
        <form method="POST" action="{{ route('payments.write-offs.store') }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <select name="storage_invoice_id" required class="field">
                @foreach ($invoices as $inv)
                    <option value="{{ $inv->id }}">{{ $inv->number ?? '#'.$inv->id }}</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" required class="field" placeholder="Amount">
            <input name="reason" required class="field md:col-span-2" placeholder="Reason">
            <div class="md:col-span-4"><button class="btn-primary" type="submit">Request write-off</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm text-left">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Customer</th><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $row->invoice?->number ?? '#'.$row->storage_invoice_id }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $row->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $row->status }}</td>
                        <td class="px-4 py-3">
                            @if ($row->status === 'pending' && $desk->canApproveWriteOff($user))
                                <div class="flex gap-1">
                                    <form method="POST" action="{{ route('payments.write-offs.decide', $row) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                                    <form method="POST" action="{{ route('payments.write-offs.decide', $row) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No write-off requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
