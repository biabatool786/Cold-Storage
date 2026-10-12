<x-layouts.authenticated title="Power-cut & backup logs">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Power-cut &amp; backup logs</h2>
        <p class="mt-1 text-sm text-muted">Record outages and backup switching. Manual events only — no live feed.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($desk->canLogPowerEvent($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('energy.power-events.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/energy/power-events'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Type
                <select name="event_type" class="field mt-1">
                    <option value="power_cut">Power cut</option>
                    <option value="backup_switch">Backup switch</option>
                </select>
            </label>
            <label class="text-sm">Chamber
                <select name="chamber_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($chambers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Started<input name="started_at" type="datetime-local" required class="field mt-1"></label>
            <label class="text-sm">Ended<input name="ended_at" type="datetime-local" class="field mt-1"></label>
            <label class="text-sm">Backup source<input name="backup_source" class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Notes<textarea name="notes" class="field mt-1" rows="2"></textarea></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Log event</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Type</th><th class="px-4 py-3">Start</th><th class="px-4 py-3">End</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($events as $e)
                    <tr>
                        <td class="px-4 py-3">{{ $e->event_type }}</td>
                        <td class="px-4 py-3">{{ $e->started_at }}</td>
                        <td class="px-4 py-3">{{ $e->ended_at ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $e->status }}</td>
                        <td class="px-4 py-3">
                            @if ($desk->canLogPowerEvent($user) && $e->status === 'open')
                                <form method="POST" action="{{ route('energy.power-events.close', $e) }}" class="inline">@csrf
                                    <input type="hidden" name="ended_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                                    <button class="btn-ghost" type="submit">Close</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No power events.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
