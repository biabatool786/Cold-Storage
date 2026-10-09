<x-layouts.authenticated :title="$tenant->name">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $tenant->name }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $tenant->email }} · {{ $tenant->city ?: 'No city' }}</p>
        </div>
        <x-status-badge :status="$tenant->status" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Status</h3>
            <form method="POST" action="{{ route('tenants.status', $tenant) }}" class="mt-4 flex gap-2">
                @csrf
                @method('PUT')
                <select name="status" class="field">
                    @foreach (\App\Enums\TenantStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($tenant->status === $status)>{{ $status->value }}</option>
                    @endforeach
                </select>
                <button class="btn-primary" type="submit">Update</button>
            </form>
            <a href="{{ route('tenants.edit', $tenant) }}" class="mt-4 inline-block text-sm font-medium text-accent">Edit details</a>
        </section>

        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Subscription</h3>
            @if ($subscription)
                <p class="mt-3 text-sm">{{ $subscription->plan?->name ?? 'Plan' }}</p>
                <div class="mt-2"><x-status-badge :status="$subscription->status" /></div>
            @else
                <p class="mt-3 text-sm text-muted">No subscription recorded.</p>
            @endif
            <p class="mt-4 text-sm text-muted">{{ $tenant->users_count }} user accounts.</p>
        </section>
    </div>

    <section class="mt-6 rounded-2xl border border-line bg-panel p-5">
        <h3 class="font-semibold">Assign Tenant Admin</h3>
        <form method="POST" action="{{ route('tenants.admins.store', $tenant) }}" class="mt-4 grid gap-3 md:grid-cols-2">
            @csrf
            <input name="name" value="{{ old('name') }}" placeholder="Name" class="field" required>
            <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" class="field" required>
            <input name="password" type="password" placeholder="Password" class="field" required>
            <input name="password_confirmation" type="password" placeholder="Confirm password" class="field" required>
            <button class="btn-primary md:col-span-2 w-fit" type="submit">Assign Tenant Admin</button>
        </form>
        @foreach (['name', 'email', 'password'] as $field)
            @error($field)<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        @endforeach
    </section>

    <section class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <h3 class="px-4 pt-4 font-semibold">Tenant users</h3>
        <table class="mt-2 min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($users as $account)
                    <tr>
                        <td class="px-4 py-3">{{ $account->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $account->email }}</td>
                        <td class="px-4 py-3">{{ $account->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-muted">No users for this cold storage.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.authenticated>
