<x-layouts.authenticated title="Gate {{ $entry->vehicle_no }}">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $entry->vehicle_no }}</h2>
        <p class="mt-1 text-sm text-muted">{{ $entry->purpose }} · {{ $entry->status }}</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Weighbridge</h3>
            <ul class="mt-3 space-y-1 text-sm">
                @forelse ($entry->weighments as $ticket)
                    <li>
                        <a href="{{ route('weighments.show', $ticket) }}">{{ $ticket->number ?? '#'.$ticket->id }}</a>
                        · Gross {{ $ticket->gross_weight ?? '—' }} · Tare {{ $ticket->tare_weight ?? '—' }} · Net {{ $ticket->net_weight ?? '—' }}
                    </li>
                @empty
                    <li class="text-muted">No tickets yet.</li>
                @endforelse
            </ul>
            @if ((auth()->user()->isSuperAdmin() || auth()->user()->hasRole(\App\Enums\RoleName::Gatekeeper)) && auth()->user()->hasPermission('weighbridge.execute') && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.weigh', $entry) }}" class="mt-4 grid gap-2">
                    @csrf
                    <div class="flex gap-2">
                        <select name="kind" class="field w-auto"><option value="gross">Gross</option><option value="tare">Tare</option></select>
                        <input name="weight" type="number" step="0.001" required class="field" placeholder="Weight">
                    </div>
                    <label class="text-sm"><input type="checkbox" name="manual" value="1"> Indicator offline / manual</label>
                    <input name="reason" placeholder="Manual reason" class="field">
                    <button class="btn-primary" type="submit">Record</button>
                </form>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Inspection and GRN</h3>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($entry->inspections as $inspection)
                    <li>{{ $inspection->result }}</li>
                @endforeach
                @foreach ($entry->goodsReceipts as $receipt)
                    <li>GRN {{ $receipt->id }} · {{ $receipt->status }}</li>
                @endforeach
            </ul>
            @if (auth()->user()->hasPermission('grn.create') && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.inspect', $entry) }}" class="mt-4 flex gap-2">
                    @csrf
                    <select name="result" class="field w-auto"><option value="accept">Accept</option><option value="partial">Partial</option><option value="reject">Reject</option></select>
                    <button class="btn-ghost" type="submit">Inspect</button>
                </form>
                <form method="POST" action="{{ route('gates.grn', $entry) }}" class="mt-3 grid gap-2">
                    @csrf
                    <input name="customer_id" type="number" required placeholder="Customer id" class="field">
                    <input name="commodity_id" type="number" required placeholder="Product id" class="field">
                    <input name="quantity" type="number" step="0.001" required placeholder="Quantity" class="field">
                    <button class="btn-primary" type="submit">Create GRN</button>
                </form>
            @endif
            @if (auth()->user()->hasPermission('gate.execute') && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.close', $entry) }}" class="mt-4">@csrf<button class="btn-ghost" type="submit">Mark exit</button></form>
            @endif
        </section>
    </div>
</x-layouts.authenticated>
