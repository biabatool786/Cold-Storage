<x-layouts.authenticated title="Create invoice">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Generate invoice</h2>
        <p class="mt-1 text-sm text-muted">Create a proforma or tax invoice from a lot (calculated) or delivery order (amount).</p>
    </div>
    @if ($branches->isEmpty())
        <p class="text-sm text-muted">No branch in scope.</p>
    @else
        <form method="POST" action="{{ route('invoices.store', $branches->first()) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div class="md:col-span-2">
                <label class="field-label">Branch</label>
                <select class="field" onchange="this.form.action = this.value">
                    @foreach ($branches as $branch)
                        <option value="{{ route('invoices.store', $branch) }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <select name="kind" class="field"><option value="tax">Tax invoice</option><option value="proforma">Proforma</option></select>
            <select name="customer_id" required class="field">
                <option value="">Customer</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
            <select name="storage_lot_id" class="field">
                <option value="">Lot (calculated charges)</option>
                @foreach ($lots as $lot)
                    <option value="{{ $lot->id }}">{{ $lot->lot_number }} · qty {{ $lot->quantity }}</option>
                @endforeach
            </select>
            <select name="delivery_order_id" class="field">
                <option value="">Delivery order (optional)</option>
                @foreach ($orders as $order)
                    <option value="{{ $order->id }}">DO #{{ $order->id }} · {{ $order->quantity }}</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount (required if no lot)" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create draft</button></div>
        </form>
    @endif
</x-layouts.authenticated>
