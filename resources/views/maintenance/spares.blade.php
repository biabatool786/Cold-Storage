<x-layouts.authenticated title="Spare parts">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Spare parts</h2>
            <p class="mt-1 text-sm text-muted">Stock on hand and movements.</p>
        </div>
        <a class="btn-ghost" href="{{ route('assets.index') }}">Assets</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($desk->canManageSpares($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('maintenance.spares.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select name="branch_picker" class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/maintenance/spares'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Part code<input name="part_code" required class="field mt-1"></label>
            <label class="text-sm">Name<input name="name" required class="field mt-1"></label>
            <label class="text-sm">Unit<input name="unit" class="field mt-1" value="pcs"></label>
            <label class="text-sm">Min stock<input name="min_stock" type="number" step="0.001" class="field mt-1" value="0"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Add part</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Code</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">On hand</th><th class="px-4 py-3">Min</th>
                @if ($desk->canManageSpares($user))<th class="px-4 py-3">Move</th>@endif
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($parts as $p)
                    <tr>
                        <td class="px-4 py-3">{{ $p->part_code }}</td>
                        <td class="px-4 py-3">{{ $p->name }}</td>
                        <td class="px-4 py-3">{{ $p->quantity_on_hand }} {{ $p->unit }}</td>
                        <td class="px-4 py-3">{{ $p->min_stock }}</td>
                        @if ($desk->canManageSpares($user))
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('maintenance.spares.move', $p) }}" class="flex flex-wrap gap-1">
                                    @csrf
                                    <select name="movement_type" class="field"><option value="receipt">In</option><option value="issue">Out</option></select>
                                    <input name="quantity" type="number" step="0.001" required class="field w-20" placeholder="qty">
                                    <input name="notes" class="field w-28" placeholder="note">
                                    <button class="btn-ghost" type="submit">OK</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No spare parts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
