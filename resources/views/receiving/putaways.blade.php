<x-layouts.authenticated title="Put-away tasks">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Put-away tasks</h2>
        <p class="mt-1 text-sm text-muted">Scan or select lot and slot. Blocked slots are rejected.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
    <div class="space-y-4">
        @forelse ($tasks as $task)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ $task->lot?->lot_number ?? 'Lot #'.$task->storage_lot_id }}</p>
                        <p class="text-muted">GRN {{ $task->receipt?->number ?? '#'.$task->goods_receipt_id }} · {{ $task->status }}
                            · suggested {{ $task->suggestedSlot?->code ?? '—' }}
                            @if($task->slot) · assigned {{ $task->slot->code }}@endif
                        </p>
                    </div>
                </div>
                @if ($task->status === 'pending' && $desk->canPutAway(auth()->user()))
                    <form method="POST" action="{{ route('putaways.execute', $task) }}" class="mt-3 grid gap-2 md:grid-cols-3">
                        @csrf
                        <select name="slot_id" class="field" required>
                            @foreach ($slots->where('chamber_id', $task->chamber_id) as $slot)
                                <option value="{{ $slot->id }}" @selected((int)$task->suggested_slot_id === (int)$slot->id)>{{ $slot->code }}</option>
                            @endforeach
                        </select>
                        <input name="reason" placeholder="Override reason (Supervisor)" class="field">
                        <button class="btn-primary" type="submit">Complete put-away</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-line bg-panel px-4 py-6 text-muted">No put-away tasks.</p>
        @endforelse
    </div>
</x-layouts.authenticated>
