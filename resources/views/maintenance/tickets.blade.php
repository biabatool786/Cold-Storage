<x-layouts.authenticated title="Breakdown tickets">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Breakdown tickets</h2>
            <p class="mt-1 text-sm text-muted">Report and track equipment failures.</p>
        </div>
        @if ($desk->canCreateWorkOrder($user) || $desk->canUpdateWork($user) || $desk->canViewAssets($user))
            <a class="btn-ghost" href="{{ route('maintenance.work-orders') }}">Work orders</a>
        @endif
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canReportBreakdown($user))
        <form method="POST" action="{{ route('maintenance.tickets.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm md:col-span-2">Asset
                <select name="facility_asset_id" required class="field mt-1">
                    @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} — {{ $a->displayName() }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Priority
                <select name="priority" class="field mt-1">
                    @foreach (['low','medium','high','critical'] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm md:col-span-2">Issue<textarea name="description" required class="field mt-1" rows="3"></textarea></label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">{{ $desk->canAssign($user) ? 'Raise ticket' : 'Report breakdown' }}</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($tickets as $t)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('maintenance.tickets.show', $t) }}">{{ $t->number }}</a></td>
                        <td class="px-4 py-3">{{ $t->asset?->asset_code }}</td>
                        <td class="px-4 py-3">{{ $t->priority }}</td>
                        <td class="px-4 py-3">{{ $t->status }}</td>
                        <td class="px-4 py-3 text-xs text-muted">{{ \Illuminate\Support\Str::limit($t->description, 40) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
