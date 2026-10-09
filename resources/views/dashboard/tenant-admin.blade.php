<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($dashboard['stats'] as $stat)
        <x-kpi :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" />
    @endforeach
</div>

@if ($dashboard['subscription'])
    <section class="mt-6 rounded-2xl border border-line bg-panel p-5">
        <h3 class="text-lg font-semibold">Subscription</h3>
        <p class="mt-2 text-sm text-muted">{{ $dashboard['subscription']->plan?->name ?? 'Plan' }}</p>
        <div class="mt-3"><x-status-badge :status="$dashboard['subscription']->status" /></div>
    </section>
@endif

<section class="mt-6 rounded-2xl border border-line bg-panel p-5">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold">Users</h3>
        <a href="{{ route('users.index') }}" class="text-sm font-medium text-accent">Users & Roles</a>
    </div>
    @include('dashboard.partials.users', ['users' => $dashboard['users']])
</section>
