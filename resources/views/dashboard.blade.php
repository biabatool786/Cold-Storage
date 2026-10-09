<x-layouts.authenticated title="Dashboard">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">Dashboard</h2>
        <p class="mt-1 text-sm text-muted">
            {{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}
            · {{ $user->isSuperAdmin() ? 'Platform' : ($user->tenant?->name ?? 'Tenant') }}
        </p>
    </div>

    @if ($dashboard['view'] === 'super-admin')
        @include('dashboard.super-admin')
    @elseif ($dashboard['view'] === 'tenant-admin')
        @include('dashboard.tenant-admin')
    @elseif ($dashboard['view'] === 'manager')
        @include('dashboard.manager')
    @else
        @include('dashboard.modules')
    @endif
</x-layouts.authenticated>
