<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($dashboard['stats'] as $stat)
        <x-kpi :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" />
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section id="tenants" class="rounded-2xl border border-line bg-panel p-5 xl:col-span-2">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="text-lg font-semibold">Tenants</h3>
                <p class="text-sm text-muted">Cold storage companies on the platform.</p>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                <label for="status" class="text-xs font-medium uppercase tracking-wide text-muted">Status</label>
                <select id="status" name="status" class="field w-auto py-2">
                    <option value="">All</option>
                    @foreach (\App\Enums\TenantStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($dashboard['status'] === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
                <button class="btn-ghost px-3 py-2" type="submit">Filter</button>
            </form>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Name</th>
                        <th class="py-2 pr-4 font-medium">City</th>
                        <th class="py-2 pr-4 font-medium">Users</th>
                        <th class="py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($dashboard['tenants'] as $tenant)
                        <tr>
                            <td class="py-3 pr-4 font-medium">{{ $tenant->name }}</td>
                            <td class="py-3 pr-4 text-muted">{{ $tenant->city ?: '—' }}</td>
                            <td class="py-3 pr-4">{{ $tenant->users_count }}</td>
                            <td class="py-3"><x-status-badge :status="$tenant->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-muted">No tenants match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-line bg-panel p-5">
        <h3 class="text-lg font-semibold">Approvals</h3>
        <p class="text-sm text-muted">Records waiting across every cold store.</p>
        <p class="mt-4 text-3xl font-semibold">{{ $dashboard['pending'] }}</p>
    </section>
</div>

<section class="mt-6 rounded-2xl border border-line bg-panel p-5">
    <h3 class="text-lg font-semibold">Recent accounts</h3>
    <p class="text-sm text-muted">Newest users across tenants.</p>
    @include('dashboard.partials.users', ['users' => $dashboard['users']])
</section>
