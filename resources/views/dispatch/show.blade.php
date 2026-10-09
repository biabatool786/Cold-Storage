<x-layouts.authenticated title="Delivery order">
    @php
        $user = auth()->user();
        $canEdit = $desk->canEditDraft($user, $order) || ($desk->canEditApproved($user) && ! $order->isEditableDraft());
        $canCancelOrder = $desk->canCancel($user) && ! in_array($order->status, ['dispatched', 'cancelled', 'rejected'], true);
        $showPickForm = in_array($order->status, ['approved', 'picking'], true) && $desk->canPick($user);
        $showTemp = in_array($order->status, ['approved', 'picking', 'picked', 'loading'], true) && ($desk->canPick($user) || $desk->canCreate($user));
        $showLoadVerify = in_array($order->status, ['picked', 'loading'], true) && $desk->canLoad($user);
        $showIssuePass = $order->loading_verified && in_array($order->status, ['loading', 'picked'], true) && $desk->canIssuePass($user);
        $showExit = $order->pass && $order->pass->status === 'issued' && $desk->canVerifyExit($user);
    @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $order->number ?? 'DO #'.$order->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $order->status }} · Lot {{ $order->lot?->lot_number }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($desk->canPrint($user))
                <a class="btn-ghost" href="{{ route('deliveries.print', $order) }}" target="_blank">Dispatch note</a>
                @if ($order->pass)
                    <a class="btn-ghost" href="{{ route('passes.print', $order->pass) }}" target="_blank">Gate pass</a>
                @endif
            @endif
            @if ($canEdit)
                <a class="btn-ghost" href="{{ route('deliveries.edit', $order) }}">Edit</a>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-2 text-sm">
        <div class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-2 font-semibold">Order</h3>
            <p>Customer: {{ $order->customer?->name }}</p>
            <p>Product: {{ $order->commodity?->name ?? $order->lot?->commodity?->name }}</p>
            <p>Requested: {{ $order->quantity }} · Picked: {{ $order->picked_qty }} · Remaining: {{ $order->remaining_qty }}</p>
            <p>Pick mode: {{ strtoupper($order->pick_mode) }}@if ($order->fifo_override) · override @endif</p>
            <p>Temp: {{ $order->outgoing_temperature ?? '—' }} · Loaders: {{ $order->loader_count ?? '—' }}</p>
        </div>
        <div class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-2 font-semibold">Checks</h3>
            @if ($checks)
                <p>Stock: {{ $checks['stock_ok'] ? 'OK' : 'Blocked' }} (avail {{ $checks['available'] }})</p>
                <p>Credit: {{ $checks['credit_ok'] ? 'OK' : 'Over limit' }}</p>
                @if ($duesDetail)
                    <p>Dues: {{ $checks['dues'] }} · Limit: {{ $checks['credit_limit'] }}</p>
                @else
                    <p>Dues flag: {{ $order->has_dues_flag ? 'Outstanding' : 'Clear' }}</p>
                @endif
            @endif
            @if ($order->dues_recommend_note)
                <p class="mt-2 text-muted">Accountant note: {{ $order->dues_recommend_note }}</p>
            @endif
        </div>
    </div>

    @if ($order->status === 'pending' && $desk->canRecommendDues($user) && $order->has_dues_flag)
        <form method="POST" action="{{ route('deliveries.recommend', $order) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="note" required placeholder="Recommend dues override" class="field">
            <button class="btn-ghost" type="submit">Recommend</button>
        </form>
    @endif

    @if ($order->status === 'pending' && $desk->canApprove($user))
        <form method="POST" action="{{ route('deliveries.approve', $order) }}" class="mb-4">
            @csrf
            <button class="btn-primary" type="submit">Approve DO</button>
        </form>
    @endif

    @if ($canCancelOrder)
        <div class="mb-4 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('deliveries.reject', $order) }}" class="flex gap-2">
                @csrf
                <input name="reason" required placeholder="Reject reason" class="field">
                <button class="btn-ghost" type="submit">Reject</button>
            </form>
            <form method="POST" action="{{ route('deliveries.cancel', $order) }}" class="flex gap-2">
                @csrf
                <input name="reason" required placeholder="Cancel reason" class="field">
                <button class="btn-ghost" type="submit">Cancel</button>
            </form>
        </div>
    @endif

    @if ($showPickForm)
        <form method="POST" action="{{ route('deliveries.pick-list', $order) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <h3 class="md:col-span-3 font-semibold">Generate pick list</h3>
            <label class="text-sm flex items-center gap-2"><input type="checkbox" name="override" value="1"> Override FIFO/FEFO</label>
            <input name="reason" placeholder="Override reason" class="field md:col-span-2">
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Generate pick list</button></div>
        </form>
    @endif

    @if ($order->pickLines->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-line bg-panel p-5">
            <h3 class="mb-3 font-semibold">Pick &amp; scan</h3>
            @foreach ($order->pickLines as $line)
                <div class="mb-3 border-b border-line pb-3 text-sm">
                    <p class="font-medium">{{ $line->lot?->lot_number }} · slot {{ $line->slot?->code }} · suggested {{ $line->suggested_qty }} · {{ $line->status }}</p>
                    @if ($line->status === 'pending' && $desk->canPick($user) && $order->status === 'picking')
                        <form method="POST" action="{{ route('deliveries.pick', $line) }}" class="mt-2 flex flex-wrap gap-2">
                            @csrf
                            <input name="picked_qty" type="number" min="0.001" step="0.001" value="{{ $line->suggested_qty }}" required class="field w-auto">
                            <input name="slot_id" type="number" value="{{ $line->slot_id }}" placeholder="Scan slot id" class="field w-auto">
                            <button class="btn-primary" type="submit">Confirm pick</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($showTemp)
        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <form method="POST" action="{{ route('deliveries.temperature', $order) }}" class="rounded-2xl border border-line bg-panel p-5 flex gap-2">
                @csrf
                <input name="temperature" type="number" step="0.01" required placeholder="Outgoing °C" class="field">
                <button class="btn-ghost" type="submit">Save temp</button>
            </form>
            <form method="POST" action="{{ route('deliveries.loaders', $order) }}" class="rounded-2xl border border-line bg-panel p-5 grid gap-2">
                @csrf
                <input name="loader_count" type="number" min="0" required placeholder="Loader count" class="field">
                <input name="loader_notes" placeholder="Loader notes" class="field">
                <button class="btn-ghost" type="submit">Save loaders</button>
            </form>
        </div>
    @endif

    @if ($showLoadVerify)
        <form method="POST" action="{{ route('deliveries.verify-loading', $order) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="final_count" type="number" step="0.001" required value="{{ $order->picked_qty }}" placeholder="Final count" class="field">
            <button class="btn-primary" type="submit">Verify loading</button>
        </form>
    @endif

    @if ($showIssuePass)
        <form method="POST" action="{{ route('deliveries.pass', $order) }}" class="mb-4">
            @csrf
            <button class="btn-primary" type="submit">Generate gate pass</button>
        </form>
    @endif

    @if ($showExit)
        <form method="POST" action="{{ route('passes.verify', $order->pass) }}" class="mb-4">
            @csrf
            <button class="btn-primary" type="submit">Verify gate pass at exit</button>
        </form>
    @endif

    @if ($desk->canDelete($user))
        <form method="POST" action="{{ route('deliveries.destroy', $order) }}" class="flex gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            @method('DELETE')
            <input name="reason" required placeholder="Delete reason" class="field">
            <button class="btn-ghost" type="submit">Delete DO</button>
        </form>
    @endif
</x-layouts.authenticated>
