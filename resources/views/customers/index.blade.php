<x-layouts.authenticated title="Customers">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Customers</h2>
            <p class="mt-1 text-sm text-muted">Party records for the branches you can work in.</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="Search name" class="field sm:w-48">
            @unless($master->seesNameOnly(auth()->user()))
                <select name="status" class="field w-auto">
                    <option value="">Any status</option>
                    @foreach (['pending', 'approved', 'hold', 'blacklisted'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @endunless
            <button class="btn-ghost" type="submit">Filter</button>
        </form>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif

    @if (auth()->user()->hasPermission('customers.create') && $branches->isNotEmpty())
        <form method="POST" action="{{ route('customers.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div class="md:col-span-2">
                <label class="field-label" for="branch_target">Branch</label>
                <select id="branch_target" class="field" onchange="this.form.action = this.value">
                    @foreach ($branches as $branch)
                        <option value="{{ route('customers.store', $branch) }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <input name="name" required placeholder="Customer name" class="field">
            <input name="phone" placeholder="Phone" class="field">
            <input name="email" type="email" placeholder="Email" class="field">
            <input name="city" placeholder="City" class="field">
            @if ($master->canEditBilling(auth()->user()))
                <input name="tax_no" placeholder="Tax number" class="field">
                <input name="billing_address" placeholder="Billing address" class="field">
            @endif
            @if ($master->canSetCredit(auth()->user()))
                <input name="credit_limit" type="number" min="0" step="0.01" placeholder="Credit limit" class="field">
            @endif
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Save customer</button></div>
        </form>
    @endif

    @if ($master->canImport(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('customers.import', $branches->first()) }}" enctype="multipart/form-data" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input type="file" name="file" accept=".csv,.xlsx" required class="field max-w-xs">
            <button class="btn-ghost" type="submit">Import Excel or CSV</button>
            @if (auth()->user()->hasPermission('customers.print'))
                <a class="btn-ghost" href="{{ route('customers.export') }}">Export</a>
            @endif
        </form>
    @elseif (auth()->user()->hasPermission('customers.print'))
        <p class="mb-4"><a class="btn-ghost" href="{{ route('customers.export') }}">Export</a></p>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Name</th>
                @unless($master->seesNameOnly(auth()->user()))
                    <th class="px-4 py-3">Phone</th><th class="px-4 py-3">Status</th>
                    @if ($master->seesFinancials(auth()->user()))<th class="px-4 py-3">Credit limit</th>@endif
                @endunless
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                        @unless($master->seesNameOnly(auth()->user()))
                            <td class="px-4 py-3">{{ $customer->phone ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $customer->status }}</td>
                            @if ($master->seesFinancials(auth()->user()))<td class="px-4 py-3">{{ $customer->credit_limit }}</td>@endif
                        @endunless
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-muted" colspan="4">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.authenticated>
