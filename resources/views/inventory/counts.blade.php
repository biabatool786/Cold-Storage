<x-layouts.authenticated title="Counts">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Cycle counts / physical audit</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if (($desk->canCreateCycleCount(auth()->user()) || $desk->canCreatePhysicalAudit(auth()->user())) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('inventory.counts.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="kind" class="field">
                @if ($desk->canCreateCycleCount(auth()->user()))<option value="cycle">Cycle count</option>@endif
                @if ($desk->canCreatePhysicalAudit(auth()->user()))<option value="physical">Full physical audit</option>@endif
            </select>
            <select name="chamber_id" class="field"><option value="">All chambers (branch default)</option>@foreach($chambers as $ch)<option value="{{ $ch->id }}">{{ $ch->code }} — {{ $ch->name }}</option>@endforeach</select>
            <input name="notes" placeholder="Notes" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create task</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($counts as $count)
            <a href="{{ route('inventory.counts.show', $count) }}" class="block rounded-2xl border border-line bg-panel p-5 text-sm">
                <p class="font-semibold">{{ ucfirst($count->kind) }} #{{ $count->id }} · {{ $count->status }}</p>
                <p class="text-muted">{{ $count->chamber?->code ?? 'All chambers' }} · {{ $count->created_at }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.authenticated>
