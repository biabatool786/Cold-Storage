<x-layouts.authenticated title="Preventive maintenance">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Preventive maintenance</h2>
            <p class="mt-1 text-sm text-muted">Upcoming, due, and overdue schedules.</p>
        </div>
        <a class="btn-ghost" href="{{ route('assets.index') }}">Assets</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCreatePm($user))
        <form method="POST" action="{{ route('maintenance.schedules.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm md:col-span-3">Asset
                <select name="facility_asset_id" required class="field mt-1">
                    @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} — {{ $a->displayName() }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Type<input name="maintenance_type" required class="field mt-1" value="inspection"></label>
            <label class="text-sm">Frequency (days)<input name="frequency_days" type="number" min="1" required class="field mt-1" value="30"></label>
            <label class="text-sm">Next due<input name="next_due_on" type="date" required class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Notes<textarea name="notes" class="field mt-1" rows="2"></textarea></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Create schedule</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Asset</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Due</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($schedules as $s)
                    <tr>
                        <td class="px-4 py-3">{{ $s->asset?->asset_code }}</td>
                        <td class="px-4 py-3">{{ $s->maintenance_type }} / every {{ $s->frequency_days }}d</td>
                        <td class="px-4 py-3">{{ optional($s->next_due_on)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $s->status }}</td>
                        <td class="px-4 py-3">
                            @if (($desk->canCreatePm($user) || $desk->canUpdateWork($user)) && $s->status !== 'completed')
                                <form method="POST" action="{{ route('maintenance.schedules.complete', $s) }}" class="inline">@csrf
                                    <input type="hidden" name="notes" value="Completed PM">
                                    <button class="btn-ghost" type="submit">Complete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No schedules.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
