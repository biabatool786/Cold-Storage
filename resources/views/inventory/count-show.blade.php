<x-layouts.authenticated title="Count task">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ ucfirst($count->kind) }} #{{ $count->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $count->status }} · {{ $count->chamber?->code ?? 'All chambers' }}</p>
        </div>
        @if ($count->status === 'open' && $desk->canCount(auth()->user()))
            <form method="POST" action="{{ route('inventory.counts.complete', $count) }}">@csrf<button class="btn-ghost" type="submit">Complete count</button></form>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="space-y-3">
        @foreach ($count->lines as $line)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <p class="font-semibold">{{ $line->lot?->lot_number }} · system {{ $line->system_qty }}
                    @if($line->counted_qty !== null) · counted {{ $line->counted_qty }} · variance {{ $line->variance }}@endif
                </p>
                @if ($count->status === 'open' && $desk->canCount(auth()->user()))
                    <form method="POST" action="{{ route('inventory.counts.lines', $line) }}" class="mt-2 flex gap-2">
                        @csrf
                        <input name="counted_qty" type="number" min="0" step="0.001" required value="{{ $line->counted_qty }}" class="field">
                        <button class="btn-primary" type="submit">Save count</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
