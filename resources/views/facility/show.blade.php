<x-layouts.authenticated :title="$chamber->code">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $chamber->code }} · {{ $chamber->name }}</h2>
        <p class="mt-1 text-sm text-muted">Capacity {{ $chamber->capacity ?? '—' }} {{ $chamber->capacity_unit }}</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @unless($layout->capacityOnly(auth()->user()))
        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">Chamber</h3>
                <p class="mt-2 text-sm">Status {{ $chamber->status }} · Temperature {{ $chamber->set_point ?? '—' }} · Humidity {{ $chamber->humidity_set_point ?? '—' }}</p>
                @if ($layout->canEdit(auth()->user()))
                    <form method="POST" action="{{ route('chambers.update', $chamber) }}" class="mt-4 grid gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $chamber->name }}" required class="field">
                        <input name="capacity" type="number" min="0" step="0.001" value="{{ $chamber->capacity }}" placeholder="Capacity" class="field">
                        <input name="capacity_unit" value="{{ $chamber->capacity_unit }}" class="field">
                        <button class="btn-primary" type="submit">Save chamber</button>
                    </form>
                @endif
                @if ($layout->canOperate(auth()->user()))
                    <form method="POST" action="{{ route('chambers.status', $chamber) }}" class="mt-3 flex gap-2">
                        @csrf
                        <select name="status" class="field w-auto">
                            @unless(auth()->user()->hasRole(\App\Enums\RoleName::Supervisor))
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                            @endunless
                            <option value="defrost">Defrost</option>
                        </select>
                        <button class="btn-ghost" type="submit">{{ auth()->user()->hasRole(\App\Enums\RoleName::Supervisor) ? 'Request defrost' : 'Change status' }}</button>
                    </form>
                @endif
                @if ($layout->canDelete(auth()->user()))
                    <form method="POST" action="{{ route('chambers.destroy', $chamber) }}" class="mt-3 flex gap-2">
                        @csrf @method('DELETE')
                        <input name="reason" required placeholder="Reason" class="field">
                        <button class="btn-ghost" type="submit">Remove chamber</button>
                    </form>
                @endif
            </section>
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">Set-points</h3>
                @foreach ($chamber->setPointChanges as $change)
                    <p class="mt-2 text-sm">{{ $change->temperature ?? '—' }}° / {{ $change->humidity ?? '—' }}% · {{ $change->status }}
                        @if ($change->status === 'pending' && $layout->canApproveSetPoint(auth()->user()))
                            <form method="POST" action="{{ route('chambers.set-points.decide', $change) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost px-2 py-1" type="submit">Approve</button></form>
                            <form method="POST" action="{{ route('chambers.set-points.decide', $change) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost px-2 py-1" type="submit">Reject</button></form>
                        @endif
                    </p>
                @endforeach
                @if ($layout->canProposeSetPoint(auth()->user()))
                    <form method="POST" action="{{ route('chambers.set-points.store', $chamber) }}" class="mt-4 flex gap-2">
                        @csrf
                        <input name="temperature" type="number" step="0.01" placeholder="Temperature" class="field">
                        <input name="humidity" type="number" step="0.01" placeholder="Humidity" class="field">
                        <button class="btn-ghost" type="submit">Submit</button>
                    </form>
                @endif
            </section>
        </div>
    @endunless
    <section class="rounded-2xl border border-line bg-panel p-5">
        <h3 class="font-semibold">Racks, levels, and slots</h3>
        @if ($layout->canCreate(auth()->user()))
            <form method="POST" action="{{ route('racks.store', $chamber) }}" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <input name="name" required placeholder="Rack name" class="field w-auto">
                <input name="code" required placeholder="Code" class="field w-auto">
                <input name="capacity" type="number" min="0" step="0.001" placeholder="Capacity" class="field w-auto">
                <button class="btn-primary" type="submit">Add rack</button>
            </form>
        @endif
        @forelse ($chamber->racks as $rack)
            <div class="mt-4 rounded-xl border border-line p-4">
                <p class="font-medium">{{ $rack->code }} · {{ $rack->name }}</p>
                @if ($layout->canEdit(auth()->user()))
                    <form method="POST" action="{{ route('racks.update', $rack) }}" class="mt-2 flex gap-2">@csrf @method('PUT')<input name="name" value="{{ $rack->name }}" class="field"><button class="btn-ghost" type="submit">Save rack</button></form>
                @endif
                @if ($layout->canCreate(auth()->user()))
                    <form method="POST" action="{{ route('levels.store', $rack) }}" class="mt-2 flex gap-2">@csrf<input name="name" required placeholder="Level" class="field w-auto"><input name="code" required placeholder="Code" class="field w-auto"><button class="btn-ghost" type="submit">Add level</button></form>
                @endif
                @foreach ($rack->levels as $level)
                    <div class="mt-3">
                        <p class="text-sm font-medium">{{ $level->code }} · {{ $level->name }}</p>
                        @if ($layout->canCreate(auth()->user()))
                            <form method="POST" action="{{ route('slots.store', $level) }}" class="mt-2 flex gap-2">@csrf<input name="code" required placeholder="Slot code" class="field w-auto"><button class="btn-ghost" type="submit">Add slot</button></form>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($level->slots as $slot)
                                <div class="rounded-lg border border-line px-3 py-2 text-sm {{ $slot->status === 'blocked' ? 'bg-rose-50' : '' }}">
                                    {{ $slot->code }} · {{ $slot->status }}
                                    @if ($slot->blocked_at)<span class="text-muted">{{ $slot->blocked_at->format('Y-m-d H:i') }}</span>@endif
                                    @if ($layout->canOperate(auth()->user()))
                                        <form method="POST" action="{{ route('slots.block', $slot) }}" class="mt-1 flex gap-1">
                                            @csrf
                                            <input type="hidden" name="blocked" value="{{ $slot->status === 'blocked' ? 0 : 1 }}">
                                            <input name="reason" required placeholder="Reason" class="field w-28 py-1">
                                            <button class="btn-ghost px-2 py-1" type="submit">{{ $slot->status === 'blocked' ? 'Unblock' : 'Block' }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="mt-3 text-sm text-muted">No racks yet. Inventory quantities are not stored on this layout.</p>
        @endforelse
    </section>
</x-layouts.authenticated>
