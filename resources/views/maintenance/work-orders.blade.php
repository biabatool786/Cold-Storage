<x-layouts.authenticated title="Work orders">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Work orders</h2>
            <p class="mt-1 text-sm text-muted">Maintenance requests and execution.</p>
        </div>
        <a class="btn-ghost" href="{{ route('maintenance.tickets') }}">Tickets</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCreateWorkOrder($user))
        <form method="POST" action="{{ route('maintenance.work-orders.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm md:col-span-2">Asset
                <select name="facility_asset_id" required class="field mt-1">
                    @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} — {{ $a->displayName() }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Ticket (optional)
                <select name="breakdown_ticket_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($tickets as $t)<option value="{{ $t->id }}">{{ $t->number }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">PM schedule (optional)
                <select name="pm_schedule_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($schedules as $s)<option value="{{ $s->id }}">#{{ $s->id }} {{ $s->maintenance_type }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Est. cost<input name="estimated_cost" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm">Planned start<input name="planned_start" type="date" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Description<textarea name="description" required class="field mt-1" rows="2"></textarea></label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">{{ $user->hasRole(\App\Enums\RoleName::Supervisor) && ! $user->isSuperAdmin() && ! $user->hasAnyRole([\App\Enums\RoleName::Admin, \App\Enums\RoleName::Manager]) ? 'Submit request' : 'Create work order' }}</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">WO</th><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Kind</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Est.</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($orders as $wo)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('maintenance.work-orders.show', $wo) }}">{{ $wo->number }}</a></td>
                        <td class="px-4 py-3">{{ $wo->asset?->asset_code }}</td>
                        <td class="px-4 py-3">{{ $wo->kind }}</td>
                        <td class="px-4 py-3">{{ $wo->status }} / {{ $wo->cost_status }}</td>
                        <td class="px-4 py-3">{{ $wo->estimated_cost !== null ? number_format((float) $wo->estimated_cost, 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No work orders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
