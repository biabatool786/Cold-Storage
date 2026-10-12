<x-layouts.authenticated title="Consumption & cost">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Consumption &amp; cost per ton</h2>
            <p class="mt-1 text-sm text-muted">Uses recorded meter kWh and storage-lot tonnage. Missing inputs show Unavailable.</p>
        </div>
    </div>
    @if ($branches->isEmpty())
        <p class="mb-6 rounded-2xl border border-line bg-panel p-5 text-sm text-muted">No accessible branches for energy reports.</p>
    @else
    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
        <label class="text-sm">Branch
            <select name="branch_id" class="field mt-1">
                @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(($branch?->id) == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm">Chamber
            <select name="chamber_id" class="field mt-1">
                <option value="">All</option>
                @foreach ($chambers as $c)<option value="{{ $c->id }}" @selected(($chamber?->id) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm">Energy cost (optional)<input name="energy_cost" type="number" step="0.01" class="field mt-1" value="{{ $energyCost }}"></label>
        <label class="text-sm">From<input name="from" type="date" class="field mt-1" value="{{ $from }}"></label>
        <label class="text-sm">To<input name="to" type="date" class="field mt-1" value="{{ $to }}"></label>
        <div class="flex items-end gap-2">
            <button class="btn-primary" type="submit">Run</button>
            @if ($desk->canPrintReports($user) && $branch)
                <a class="btn-ghost" target="_blank" href="{{ route('energy.reports.print', array_merge(['branch' => $branch], request()->except('branch_id'))) }}">Print</a>
            @endif
        </div>
    </form>
    @if ($report)
        <div class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4 text-sm">
            <div><span class="text-muted">kWh</span><div class="font-medium">{{ number_format($report['kwh'], 3) }}</div></div>
            <div><span class="text-muted">Energy cost</span><div class="font-medium">{{ $report['energy_cost'] !== null ? number_format($report['energy_cost'], 2) : 'Unavailable' }}</div></div>
            <div><span class="text-muted">Tonnage</span><div class="font-medium">{{ $report['tonnage'] !== null ? number_format($report['tonnage'], 3) : 'Unavailable' }}</div></div>
            <div><span class="text-muted">Cost / ton</span><div class="font-medium">{{ is_numeric($report['cost_per_ton']) ? number_format($report['cost_per_ton'], 2) : $report['cost_per_ton'] }}</div></div>
        </div>
        <ul class="mt-4 space-y-1 text-sm text-muted">
            @foreach ($report['sources'] as $key => $src)
                <li><strong>{{ $key }}:</strong> {{ $src }}</li>
            @endforeach
        </ul>
    @endif
    @endif
</x-layouts.authenticated>
