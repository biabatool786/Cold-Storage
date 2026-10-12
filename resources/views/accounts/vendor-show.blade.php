<x-layouts.authenticated title="{{ $vendor->name }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $vendor->name }}</h2>
            <p class="mt-1 text-sm text-muted">Bills stay unpaid until a payment is recorded.</p>
        </div>
        <a class="btn-ghost" href="{{ route('vendors.index') }}">Vendors</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canRecordVendorBillPayment($user))
        <form method="POST" action="{{ route('vendors.bills.store', $vendor) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <input name="reference" required placeholder="Bill ref" class="field">
            <input name="bill_date" type="date" required value="{{ now()->toDateString() }}" class="field">
            <input name="amount" type="number" step="0.01" required placeholder="Amount" class="field">
            <input name="description" placeholder="Description" class="field md:col-span-3">
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Record bill</button></div>
        </form>
        <form method="POST" action="{{ route('vendors.payments.store', $vendor) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <select name="vendor_bill_id" class="field">
                <option value="">Unallocated</option>
                @foreach ($vendor->bills->where('status', '!=', 'paid') as $bill)
                    <option value="{{ $bill->id }}">{{ $bill->reference }} (out {{ number_format($bill->outstanding(), 2) }})</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" required class="field" placeholder="Pay amount">
            <input name="paid_on" type="date" required value="{{ now()->toDateString() }}" class="field">
            <select name="method" class="field"><option value="cash">Cash</option><option value="bank">Bank</option><option value="cheque">Cheque</option><option value="wallet">Wallet</option></select>
            <input name="reference" placeholder="Payment ref" class="field">
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Record payment</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel mb-4">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Bill</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Paid</th><th class="px-4 py-3">Status</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($vendor->bills as $bill)
                <tr>
                    <td class="px-4 py-3">{{ $bill->reference }}</td>
                    <td class="px-4 py-3">{{ number_format((float) $bill->amount, 2) }}</td>
                    <td class="px-4 py-3">{{ number_format((float) $bill->paid_amount, 2) }}</td>
                    <td class="px-4 py-3">{{ $bill->status }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-4 text-muted" colspan="4">No bills.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
