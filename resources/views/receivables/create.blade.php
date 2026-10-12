<x-layouts.authenticated title="Record payment">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Record payment</h2>
        <p class="mt-1 text-sm text-muted">Invoice receipt, advance or security deposit.</p>
    </div>
    <form method="POST" action="{{ route('payments.store', $branches->first()) }}" class="grid max-w-3xl gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2" id="pay-form">
        @csrf
        <label class="text-sm md:col-span-2">Branch
            <select name="branch_id" class="field mt-1" onchange="document.getElementById('pay-form').action='{{ url('/branches') }}/'+this.value+'/payments'">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Customer
            <select name="customer_id" required class="field mt-1">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Kind
            <select name="kind" class="field mt-1">
                <option value="invoice">Invoice payment</option>
                <option value="advance">Advance</option>
                <option value="security">Security deposit</option>
            </select>
        </label>
        <label class="text-sm">Invoice (optional)
            <select name="storage_invoice_id" class="field mt-1">
                <option value="">—</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->number ?? '#'.$invoice->id }} — {{ number_format((float) $invoice->amount, 2) }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Amount
            <input name="amount" type="number" step="0.01" min="0.01" required class="field mt-1">
        </label>
        <label class="text-sm">Method
            <select name="method" class="field mt-1">
                <option value="cash">Cash</option>
                <option value="bank">Bank transfer</option>
                <option value="cheque">Cheque</option>
                <option value="wallet">Wallet</option>
            </select>
        </label>
        <label class="text-sm">Paid on
            <input name="paid_on" type="date" required value="{{ now()->toDateString() }}" class="field mt-1">
        </label>
        <label class="text-sm">Reference
            <input name="reference" class="field mt-1" placeholder="Bank / wallet ref">
        </label>
        <label class="text-sm">Cheque number
            <input name="cheque_number" class="field mt-1">
        </label>
        <label class="text-sm md:col-span-2">Notes
            <textarea name="notes" class="field mt-1" rows="2"></textarea>
        </label>
        <div class="md:col-span-2"><button class="btn-primary" type="submit">Save payment</button></div>
    </form>
</x-layouts.authenticated>
