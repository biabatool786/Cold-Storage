<x-layouts.authenticated title="Branches">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Branches</h2>
            <p class="mt-1 text-sm text-muted">Branches belong to the signed-in cold store. A submitted tenant or branch id is ignored.</p>
        </div>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ auth()->user()->isSuperAdmin() ? ($tenants->isNotEmpty() ? route('tenants.branches.store', $tenants->first()) : route('branches.store')) : route('branches.store') }}" class="mt-6 grid gap-4 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            @if (auth()->user()->isSuperAdmin())
                <div class="md:col-span-2">
                    <label for="cold_store" class="field-label">Cold store</label>
                    <select id="cold_store" class="field" onchange="this.form.action = this.value">
                        @foreach ($tenants as $tenant)
                            <option value="{{ route('tenants.branches.store', $tenant) }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="field">
            </div>
            <div>
                <label for="code" class="field-label">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" required class="field">
            </div>
            <div>
                <label for="phone" class="field-label">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" class="field">
            </div>
            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" class="field">
            </div>
            <div class="md:col-span-2">
                <label for="address" class="field-label">Address</label>
                <input id="address" name="address" value="{{ old('address') }}" class="field">
            </div>
            <div>
                <button class="btn-primary" type="submit">Create branch</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Code</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($branches as $branch)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('branches.show', $branch) }}">{{ $branch->name }}</a></td>
                        <td class="px-4 py-3 text-muted">{{ $branch->code }}</td>
                        <td class="px-4 py-3">{{ $branch->status->value }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-muted">No branches in this scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
