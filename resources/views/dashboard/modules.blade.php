<div class="rounded-2xl border border-line bg-panel p-5">
    <h3 class="text-lg font-semibold">{{ $dashboard['title'] }}</h3>
    <p class="mt-1 max-w-3xl text-sm text-muted">{{ $dashboard['intro'] }}</p>
</div>

@if ($dashboard['sections'] === [])
    <section class="mt-6 rounded-2xl border border-dashed border-line bg-panel p-8 text-sm text-muted">
        No operating records are available for this role.
    </section>
@else
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ($dashboard['sections'] as $section)
            @include('dashboard.partials.section', ['section' => $section])
        @endforeach
    </div>
@endif

@if ($dashboard['users']->isNotEmpty())
    <section class="mt-6 rounded-2xl border border-line bg-panel p-5">
        <h3 class="text-lg font-semibold">Recent accounts</h3>
        @include('dashboard.partials.users', ['users' => $dashboard['users']])
    </section>
@endif
