<x-layouts.authenticated title="Tariffs">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Tariffs &amp; rate cards</h2>
        <p class="mt-1 text-sm text-muted">Storage rates, free days, grace periods and tax. Changes may require Manager approval.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canManageTariffs(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('billing.tariffs.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <select class="field md:col-span-3" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)
                    <option value="{{ route('billing.tariffs.store', $branch) }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <select name="customer_id" required class="field">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
            <input name="rate" type="number" step="0.01" required placeholder="Rate" class="field">
            <input name="unit" value="kg" class="field">
            <input name="free_days" type="number" min="0" value="0" placeholder="Free days" class="field">
            <input name="grace_days" type="number" min="0" value="0" placeholder="Grace days" class="field">
            <input name="tax_percent" type="number" step="0.01" value="0" placeholder="Tax %" class="field">
            <select name="charge_type" class="field"><option value="storage">Storage</option><option value="handling">Handling</option><option value="demurrage">Demurrage</option></select>
            <input name="change_reason" placeholder="Change reason" class="field md:col-span-2">
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Save rate card</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($rates as $rate)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-line bg-panel p-4 text-sm">
                <div>
                    <p class="font-medium">{{ $rate->customer?->name }} · {{ number_format((float) $rate->rate, 2) }}/{{ $rate->unit }}</p>
                    <p class="text-muted">{{ $rate->charge_type }} · free {{ $rate->free_days }} · grace {{ $rate->grace_days }} · tax {{ $rate->tax_percent }}% · {{ $rate->approval_status }}</p>
                </div>
                @if ($rate->approval_status === 'pending' && $desk->canApproveTariff(auth()->user()))
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('billing.tariffs.decide', $rate) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('billing.tariffs.decide', $rate) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
