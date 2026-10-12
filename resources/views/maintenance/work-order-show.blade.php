<x-layouts.authenticated title="{{ $order->number }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $order->number }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $order->status }} · {{ $order->kind }} · cost {{ $order->cost_status }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('maintenance.work-orders') }}">Back</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    <div class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3 text-sm">
        <div><span class="text-muted">Asset</span><div class="font-medium">{{ $order->asset?->asset_code }}</div></div>
        <div><span class="text-muted">Assigned</span><div class="font-medium">user #{{ $order->assigned_user_id ?: '—' }} / vendor #{{ $order->assigned_vendor_id ?: '—' }}</div></div>
        <div><span class="text-muted">Est. / Actual</span><div class="font-medium">{{ number_format((float)$order->estimated_cost,2) }} / {{ $order->actual_cost !== null ? number_format((float)$order->actual_cost,2) : '—' }}</div></div>
        <div class="md:col-span-3"><span class="text-muted">Work</span><div>{{ $order->description }}</div></div>
    </div>
    @if ($desk->canAssign($user))
        <form method="POST" action="{{ route('maintenance.work-orders.assign', $order) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-4 md:grid-cols-3">
            @csrf
            <label class="text-sm">Technician
                <select name="assigned_user_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($users as $u)<option value="{{ $u->id }}" @selected($order->assigned_user_id == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Vendor
                <select name="assigned_vendor_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($vendors as $v)<option value="{{ $v->id }}" @selected($order->assigned_vendor_id == $v->id)>{{ $v->name }}</option>@endforeach
                </select>
            </label>
            <div class="flex items-end"><button class="btn-primary" type="submit">Assign</button></div>
        </form>
    @endif
    @if ($desk->canUpdateWork($user))
        <form method="POST" action="{{ route('maintenance.work-orders.status', $order) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-4 md:grid-cols-3">
            @csrf
            <label class="text-sm">Status
                <select name="status" class="field mt-1">
                    @foreach (['open','in_progress','completed','cancelled'] as $st)
                        <option value="{{ $st }}" @selected($order->status === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Actual cost<input name="actual_cost" type="number" step="0.01" class="field mt-1" value="{{ $order->actual_cost }}"></label>
            <label class="text-sm md:col-span-2">Message<textarea name="message" required class="field mt-1" rows="2"></textarea></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Update work status</button></div>
        </form>
        <form method="POST" action="{{ route('maintenance.work-orders.issue-part', $order) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-4 md:grid-cols-3">
            @csrf
            <label class="text-sm">Spare part
                <select name="spare_part_id" required class="field mt-1">
                    @foreach ($parts as $sp)<option value="{{ $sp->id }}">{{ $sp->part_code }} ({{ $sp->quantity_on_hand }})</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Qty<input name="quantity" type="number" step="0.001" required class="field mt-1"></label>
            <label class="text-sm">Notes<input name="notes" class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Issue parts</button></div>
        </form>
    @endif
    @if ($desk->canApproveWorkOrderCost($user) && $order->cost_status === 'pending')
        <form method="POST" action="{{ route('maintenance.work-orders.decide-cost', $order) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-4">
            @csrf
            <input type="hidden" name="approve" value="1">
            <button class="btn-primary" type="submit">Approve cost</button>
        </form>
        <form method="POST" action="{{ route('maintenance.work-orders.decide-cost', $order) }}" class="mb-4 inline">
            @csrf
            <input type="hidden" name="approve" value="0">
            <button class="btn-ghost" type="submit">Reject cost</button>
        </form>
    @endif
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Log</h3>
    <ul class="space-y-2 text-sm">
        @forelse ($order->logs as $log)
            <li class="rounded-xl border border-line px-3 py-2">{{ $log->created_at }} — {{ $log->status_from }} → {{ $log->status_to }} — {{ $log->message }}</li>
        @empty
            <li class="text-muted">No log entries.</li>
        @endforelse
    </ul>
</x-layouts.authenticated>
