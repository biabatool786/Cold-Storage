<x-layouts.authenticated title="Invoices">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Invoices</h2>
            <p class="mt-1 text-sm text-muted">Proforma and tax invoices. Posted documents are immutable.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-ghost" href="{{ route('billing.accrued') }}">Accrued charges</a>
            <a class="btn-ghost" href="{{ route('billing.tariffs') }}">Tariffs</a>
            @if ($desk->canCreateInvoice(auth()->user()))
                <a class="btn-primary" href="{{ route('invoices.create') }}">Create invoice</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Number</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Kind</th>
                <th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number ?? '#'.$invoice->id }}</a></td>
                        <td class="px-4 py-3">{{ $invoice->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $invoice->kind }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $invoice->amount, 2) }}</td>
                        <td class="px-4 py-3">{{ $invoice->balanceDisplay() }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No invoices.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
