<x-layouts.authenticated title="Lot Traceability">
    <h2 class="text-2xl font-semibold tracking-tight mb-1">Lot traceability</h2>
    <p class="text-sm text-muted mb-6">Forward and backward using existing GRN, ledger, and dispatch records only.</p>
    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <select name="storage_lot_id" class="field" required>@foreach($lots as $l)<option value="{{ $l->id }}" @selected(request('storage_lot_id')==$l->id)>{{ $l->lot_number }}</option>@endforeach</select>
        <button class="btn-primary" type="submit">Trace</button>
        @if($canPrint && request('storage_lot_id'))
            <a class="btn-secondary" href="{{ route('quality.traceability.print', ['storage_lot_id' => request('storage_lot_id')]) }}">Print</a>
        @endif
    </form>
    @if($trace)
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <h3 class="font-medium mb-2">Backward</h3>
                <p>Lot: {{ $trace['lot']->lot_number }}</p>
                <p>GRN: {{ $trace['backward']['goods_receipt']['number'] ?? 'Unavailable' }}</p>
                <p>Customer: {{ $trace['backward']['customer'] ?? '—' }}</p>
                <p>Commodity: {{ $trace['backward']['commodity'] ?? '—' }}</p>
                <p>Chamber: {{ $trace['backward']['chamber'] ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <h3 class="font-medium mb-2">Forward</h3>
                <p class="mb-2">Movements: {{ count($trace['forward']['movements']) }}</p>
                <ul class="space-y-1">@foreach($trace['forward']['movements'] as $m)<li>{{ $m['type'] }} {{ $m['quantity'] }} @ {{ $m['at'] }}</li>@endforeach</ul>
                <p class="mt-3 mb-1">Dispatches: {{ count($trace['forward']['dispatches']) }}</p>
                <ul class="space-y-1">@foreach($trace['forward']['dispatches'] as $d)<li>{{ $d['number'] }} — {{ $d['status'] }}</li>@endforeach</ul>
            </div>
        </div>
        @if($trace['unavailable'])
            <div class="mt-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-amber-800">
                @foreach($trace['unavailable'] as $u)<p>{{ $u }}</p>@endforeach
            </div>
        @endif
    @endif
</x-layouts.authenticated>
