<x-layouts.authenticated title="Vendors">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Vendors &amp; suppliers</h2>
            <p class="mt-1 text-sm text-muted">Supplier master data for bills and payments.</p>
        </div>
        <a class="btn-ghost" href="{{ route('expenses.index') }}">Expenses</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCreateVendor(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('vendors.store', $branches->first()) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <input name="name" required placeholder="Vendor name" class="field">
            <input name="phone" placeholder="Phone" class="field">
            <input name="email" type="email" placeholder="Email" class="field">
            <input name="tax_no" placeholder="Tax no" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create vendor</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm text-left">
            <thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($vendors as $vendor)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('vendors.show', $vendor) }}">{{ $vendor->name }}</a></td>
                        <td class="px-4 py-3">{{ $vendor->phone ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $vendor->status }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="3">No vendors.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
