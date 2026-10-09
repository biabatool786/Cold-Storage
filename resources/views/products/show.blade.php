<x-layouts.authenticated :title="$commodity->name">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $commodity->code ? $commodity->code.' · ' : '' }}{{ $commodity->name }}</h2>
        <p class="mt-1 text-sm text-muted">Status {{ $commodity->status }} · Shrinkage {{ $commodity->shrinkage_percent ?? '—' }}%</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Product</h3>
            @if ($products->canEditIdentity(auth()->user()))
                <form method="POST" action="{{ route('commodities.update', $commodity) }}" class="mt-4 grid gap-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $commodity->name }}" required class="field">
                    <input name="code" value="{{ $commodity->code }}" placeholder="Code" class="field">
                    <button class="btn-primary" type="submit">Save product</button>
                </form>
            @endif
            @if ($products->canDeactivate(auth()->user()) && $commodity->status !== 'inactive')
                <form method="POST" action="{{ route('commodities.deactivate', $commodity) }}" class="mt-3">@csrf<button class="btn-ghost" type="submit">Deactivate</button></form>
            @endif
            @if ($products->canDelete(auth()->user()))
                <form method="POST" action="{{ route('commodities.destroy', $commodity) }}" class="mt-3 flex gap-2">
                    @csrf @method('DELETE')
                    <input name="reason" required placeholder="Reason" class="field">
                    <button class="btn-ghost" type="submit">Remove product</button>
                </form>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Storage conditions</h3>
            <p class="mt-2 text-sm">Temperature {{ $commodity->storage_temperature ?? '—' }} · Humidity {{ $commodity->storage_humidity ?? '—' }} · Shelf life {{ $commodity->shelf_life_days ?? '—' }} days</p>
            @if ($products->canEditStorage(auth()->user()))
                <form method="POST" action="{{ route('commodities.storage', $commodity) }}" class="mt-4 grid gap-2">
                    @csrf @method('PUT')
                    <input name="storage_temperature" type="number" step="0.01" value="{{ $commodity->storage_temperature }}" placeholder="Temperature" class="field">
                    <input name="storage_humidity" type="number" step="0.01" value="{{ $commodity->storage_humidity }}" placeholder="Humidity" class="field">
                    <input name="shelf_life_days" type="number" min="0" value="{{ $commodity->shelf_life_days }}" placeholder="Shelf life days" class="field">
                    <button class="btn-ghost" type="submit">Save conditions</button>
                </form>
            @endif
        </section>
        @if ($products->canViewUnits(auth()->user()))
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">Units</h3>
                @foreach ($commodity->units as $unit)
                    <p class="mt-2 text-sm">1 {{ $unit->unit }} = {{ $unit->factor_to_kg }} kg</p>
                @endforeach
                @if ($products->canEditUnits(auth()->user()))
                    <form method="POST" action="{{ route('commodities.units', $commodity) }}" class="mt-4 flex gap-2">
                        @csrf @method('PUT')
                        <select name="unit" class="field w-auto"><option value="bag">Bag</option><option value="kg">KG</option><option value="pallet">Pallet</option></select>
                        <input name="factor_to_kg" type="number" min="0.001" step="0.001" required placeholder="KG per unit" class="field">
                        <button class="btn-ghost" type="submit">Save unit</button>
                    </form>
                @endif
            </section>
        @endif
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Shrinkage</h3>
            @foreach ($commodity->shrinkageChanges as $change)
                <p class="mt-2 text-sm">{{ $change->percent }}% · {{ $change->status }}
                    @if ($change->status === 'pending' && $products->canApproveShrinkage(auth()->user()))
                        <form method="POST" action="{{ route('commodities.shrinkage.decide', $change) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost px-2 py-1" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('commodities.shrinkage.decide', $change) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost px-2 py-1" type="submit">Reject</button></form>
                    @endif
                </p>
            @endforeach
            @if ($products->canProposeShrinkage(auth()->user()))
                <form method="POST" action="{{ route('commodities.shrinkage', $commodity) }}" class="mt-4 flex gap-2">
                    @csrf
                    <input name="percent" type="number" min="0" max="100" step="0.001" required placeholder="Standard shrinkage %" class="field">
                    <button class="btn-ghost" type="submit">Submit</button>
                </form>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5 lg:col-span-2">
            <h3 class="font-semibold">Varieties and grades</h3>
            @if ($products->canCreate(auth()->user()))
                <form method="POST" action="{{ route('commodities.varieties.store', $commodity) }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input name="name" required placeholder="Variety" class="field w-auto">
                    <input name="code" required placeholder="Code" class="field w-auto">
                    <button class="btn-primary" type="submit">Add variety</button>
                </form>
            @endif
            @forelse ($commodity->varieties as $variety)
                <div class="mt-4 rounded-xl border border-line p-4">
                    <p class="font-medium">{{ $variety->code }} · {{ $variety->name }}</p>
                    @if ($products->canEditIdentity(auth()->user()))
                        <form method="POST" action="{{ route('commodities.varieties.update', $variety) }}" class="mt-2 flex gap-2">@csrf @method('PUT')<input name="name" value="{{ $variety->name }}" class="field"><button class="btn-ghost" type="submit">Save variety</button></form>
                    @endif
                    @foreach ($variety->grades as $grade)
                        <p class="mt-2 text-sm">{{ $grade->code }} · {{ $grade->name }}</p>
                        @if ($products->canEditIdentity(auth()->user()))
                            <form method="POST" action="{{ route('commodities.grades.update', $grade) }}" class="mt-1 flex gap-2">@csrf @method('PUT')<input name="name" value="{{ $grade->name }}" class="field"><button class="btn-ghost" type="submit">Save grade</button></form>
                        @endif
                    @endforeach
                    @if ($products->canCreate(auth()->user()))
                        <form method="POST" action="{{ route('commodities.grades.store', $variety) }}" class="mt-2 flex gap-2">
                            @csrf
                            <input name="name" required placeholder="Grade" class="field w-auto">
                            <input name="code" required placeholder="Code" class="field w-auto">
                            <button class="btn-ghost" type="submit">Add grade</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="mt-3 text-sm text-muted">No varieties yet.</p>
            @endforelse
        </section>
        @if ($products->canEditRules(auth()->user()) || $commodity->incompatibilities->isNotEmpty())
            <section class="rounded-2xl border border-line bg-panel p-5 lg:col-span-2">
                <h3 class="font-semibold">Incompatible products</h3>
                @foreach ($commodity->incompatibilities as $rule)
                    <p class="mt-2 text-sm">Cannot be stored with {{ $rule->other?->name }}</p>
                @endforeach
                @if ($products->canEditRules(auth()->user()))
                    <form method="POST" action="{{ route('commodities.incompatible', $commodity) }}" class="mt-4 flex gap-2">
                        @csrf
                        <select name="incompatible_commodity_id" class="field" required>
                            @foreach ($others as $other)
                                <option value="{{ $other->id }}">{{ $other->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-ghost" type="submit">Add rule</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-layouts.authenticated>
