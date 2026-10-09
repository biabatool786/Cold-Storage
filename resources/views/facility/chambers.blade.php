<x-layouts.authenticated title="Chambers">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Chambers</h2>
            <p class="mt-1 text-sm text-muted">Cold rooms on the branches you can work in.</p>
        </div>
        <form method="GET" class="flex gap-2">
            <input name="q" value="{{ $search }}" placeholder="Name or code" class="field sm:w-56">
            <button class="btn-ghost" type="submit">Filter</button>
        </form>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($layout->canCreate(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('chambers.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <div class="md:col-span-2">
                <label class="field-label">Branch</label>
                <select class="field" onchange="this.form.action = this.value">
                    @foreach ($branches as $branch)
                        <option value="{{ route('chambers.store', $branch) }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <input name="name" required placeholder="Chamber name" class="field">
            <input name="code" required placeholder="Code" class="field">
            <input name="capacity" type="number" min="0" step="0.001" placeholder="Capacity" class="field">
            <input name="capacity_unit" value="kg" placeholder="Unit" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create chamber</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Chamber</th>
                <th class="px-4 py-3">Branch</th>
                <th class="px-4 py-3">Capacity</th>
                @unless($layout->capacityOnly(auth()->user()))
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Temperature</th>
                    <th class="px-4 py-3">Humidity</th>
                @endunless
                <th class="px-4 py-3">Slots</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($chambers as $chamber)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('chambers.show', $chamber) }}">{{ $chamber->code }} · {{ $chamber->name }}</a></td>
                        <td class="px-4 py-3">{{ $chamber->branch?->name }}</td>
                        <td class="px-4 py-3">{{ $chamber->capacity ?? '—' }} {{ $chamber->capacity_unit }}</td>
                        @unless($layout->capacityOnly(auth()->user()))
                            <td class="px-4 py-3">{{ $chamber->status }}</td>
                            <td class="px-4 py-3">{{ $chamber->set_point ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $chamber->humidity_set_point ?? '—' }}</td>
                        @endunless
                        <td class="px-4 py-3">{{ $chamber->slots_count }} total · {{ $chamber->blocked_slots_count }} blocked</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-muted">No chambers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
