<x-layouts.authenticated title="{{ $ticket->number }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $ticket->number }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $ticket->status }} · {{ $ticket->priority }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('maintenance.tickets') }}">Back</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    <div class="mb-4 rounded-2xl border border-line bg-panel p-5 text-sm">
        <p class="font-medium">{{ $ticket->asset?->displayName() }} ({{ $ticket->asset?->asset_code }})</p>
        <p class="mt-2">{{ $ticket->description }}</p>
        <p class="mt-2 text-muted">Assigned user #{{ $ticket->assigned_user_id ?: '—' }} · vendor #{{ $ticket->assigned_vendor_id ?: '—' }}</p>
    </div>
    @if ($desk->canAssign($user) && ! in_array($ticket->status, ['closed'], true))
        <form method="POST" action="{{ route('maintenance.tickets.assign', $ticket) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-4 md:grid-cols-3">
            @csrf
            <label class="text-sm">Technician
                <select name="assigned_user_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Vendor
                <select name="assigned_vendor_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                </select>
            </label>
            <div class="flex items-end"><button class="btn-primary" type="submit">Assign</button></div>
        </form>
    @endif
    @if ($desk->canCloseTicket($user) && $ticket->status !== 'closed')
        <form method="POST" action="{{ route('maintenance.tickets.close', $ticket) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-4">
            @csrf
            <input name="closure_notes" required placeholder="Closure reason" class="field flex-1">
            <button class="btn-primary" type="submit">Close ticket</button>
        </form>
    @endif
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Work orders</h3>
    <ul class="mb-4 space-y-1 text-sm">
        @forelse ($ticket->workOrders as $wo)
            <li><a href="{{ route('maintenance.work-orders.show', $wo) }}">{{ $wo->number }}</a> — {{ $wo->status }}</li>
        @empty
            <li class="text-muted">None yet.</li>
        @endforelse
    </ul>
</x-layouts.authenticated>
