<x-layouts.authenticated title="Profit & loss">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Profit &amp; loss</h2>
            <p class="mt-1 text-sm text-muted">Posted invoice revenue vs posted operating expenses. Manager scope: own branch.</p>
        </div>
        @if ($branch && $desk->canPrintProfitLoss(auth()->user()))
            <a class="btn-primary" target="_blank" href="{{ route('accounts.profit-loss.print', [$branch, 'period_start' => $start, 'period_end' => $end]) }}">Print</a>
        @endif
    </div>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="branch_id" class="field">
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(($branch->id ?? null) == $b->id)>{{ $b->name }}</option>@endforeach
        </select>
        <input name="period_start" type="date" value="{{ $start }}" class="field">
        <input name="period_end" type="date" value="{{ $end }}" class="field">
        <button class="btn-primary" type="submit">View</button>
    </form>
    @if ($report)
        <div class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3 text-sm">
            <div><span class="text-muted">Revenue</span><div class="text-xl font-semibold">{{ number_format($report['revenue'], 2) }}</div></div>
            <div><span class="text-muted">Expenses</span><div class="text-xl font-semibold">{{ number_format($report['expenses'], 2) }}</div></div>
            <div><span class="text-muted">Profit</span><div class="text-xl font-semibold">{{ number_format($report['profit'], 2) }}</div></div>
        </div>
        <ul class="text-sm text-muted space-y-1">
            @foreach ($report['notes'] as $note)<li>{{ $note }}</li>@endforeach
        </ul>
    @endif
</x-layouts.authenticated>
