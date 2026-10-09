<x-layouts.authenticated title="Occupancy map">
    <h2 class="text-2xl font-semibold tracking-tight">Occupancy map</h2>
    <p class="mt-1 text-sm text-muted">Configured slots are available or blocked. Occupied quantities appear when the inventory module assigns a slot.</p>
    <div class="mt-6 space-y-6">
        @forelse ($chambers as $chamber)
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">{{ $chamber->code }} · {{ $chamber->name }}</h3>
                <p class="text-sm text-muted">Capacity {{ $chamber->capacity ?? '—' }} {{ $chamber->capacity_unit }}</p>
                @foreach ($chamber->racks as $rack)
                    <div class="mt-4">
                        <p class="text-sm font-medium">Rack {{ $rack->code }}</p>
                        @foreach ($rack->levels as $level)
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-xs uppercase tracking-wide text-muted">{{ $level->code }}</span>
                                @foreach ($level->slots as $slot)
                                    <span class="rounded-lg px-3 py-2 text-sm {{ $slot->status === 'blocked' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-50 text-emerald-800' }}">{{ $slot->code }} · {{ $slot->status }}</span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </section>
        @empty
            <p class="text-sm text-muted">No chambers to map.</p>
        @endforelse
    </div>
</x-layouts.authenticated>
