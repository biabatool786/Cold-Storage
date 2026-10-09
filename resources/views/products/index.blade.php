<x-layouts.authenticated title="Products">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Products</h2>
            <p class="mt-1 text-sm text-muted">Commodity, variety, and grade master.</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="Name or code" class="field sm:w-56">
            <button class="btn-ghost" type="submit">Filter</button>
        </form>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($products->canCreate(auth()->user()))
        <form method="POST" action="{{ $tenants->isNotEmpty() ? route('commodities.store.tenant', $tenants->first()) : route('commodities.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            @if ($tenants->isNotEmpty())
                <div class="md:col-span-2">
                    <label class="field-label">Tenant</label>
                    <select class="field" onchange="this.form.action = this.value">
                        @foreach ($tenants as $tenant)
                            <option value="{{ route('commodities.store.tenant', $tenant) }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <input name="name" required placeholder="Product name" class="field">
            <input name="code" placeholder="Code" class="field">
            <input name="variety" placeholder="Variety" class="field">
            <input name="grade" placeholder="Grade" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create product</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Shrinkage</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($commodities as $commodity)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('commodities.show', $commodity) }}">{{ $commodity->code ? $commodity->code.' · ' : '' }}{{ $commodity->name }}</a></td>
                        <td class="px-4 py-3">{{ $commodity->status }}</td>
                        <td class="px-4 py-3">{{ $commodity->shrinkage_percent ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="3">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
