@php
    use App\Enums\RoleName;
@endphp
<x-layouts.authenticated title="Users">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Users</h2>
            <p class="mt-1 text-sm text-muted">Accounts follow the signed-in tenant. A submitted tenant id is ignored.</p>
        </div>
        <form method="GET" action="{{ route('users.index') }}" class="flex gap-2">
            <label for="q" class="sr-only">Search</label>
            <input id="q" name="q" value="{{ $search }}" placeholder="Search name or email" class="field sm:w-64">
            <button class="btn-ghost" type="submit">Filter</button>
        </form>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route('users.store') }}" class="mt-6 grid gap-4 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field">
                @error('name')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field">
                @error('email')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" required class="field">
                @error('password')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="field">
            </div>
            <div>
                <label for="role" class="field-label">Role</label>
                <select id="role" name="role" class="field">
                    @foreach (RoleName::cases() as $role)
                        @if ($role->canBeAssignedTo(auth()->user()->isSuperAdmin() ? null : auth()->user()->tenant_id, auth()->user()))
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->value }}</option>
                        @endif
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary">Create user</button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Tenant</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($users as $account)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('users.show', $account) }}">{{ $account->name }}</a></td>
                        <td class="px-4 py-3 text-muted">{{ $account->email }}</td>
                        <td class="px-4 py-3">{{ $account->tenant?->name ?? 'Platform' }}</td>
                        <td class="px-4 py-3">
                            @if ($canManage && auth()->id() !== $account->id)
                                <form method="POST" action="{{ route('users.role.update', $account) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="role" class="field w-auto py-1.5">
                                        @foreach (RoleName::cases() as $role)
                                            @continue(! $role->canBeAssignedTo($account->tenant_id !== null ? (int) $account->tenant_id : null, auth()->user()))
                                            <option value="{{ $role->value }}" @selected($account->roles->contains('name', $role->value))>{{ $role->value }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-ghost px-3 py-1.5 text-xs">Save</button>
                                </form>
                            @else
                                {{ $account->roles->pluck('name')->join(', ') ?: 'No role' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-muted">No users in this scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.authenticated>
