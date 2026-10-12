<x-layouts.authenticated title="Day / cash book">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Day book / cash book</h2>
            <p class="mt-1 text-sm text-muted">From posted customer receipts, expenses and vendor payments.</p>
        </div>
        @if ($branch && $desk->canPrintDayBook(auth()->user()))
            <a class="btn-primary" target="_blank" href="{{ route('accounts.day-book.print', [$branch, 'period_start' => $start, 'period_end' => $end]) }}">Print</a>
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
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Ref</th><th class="px-4 py-3">Debit</th><th class="px-4 py-3">Credit</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($lines as $line)
                <tr>
                    <td class="px-4 py-3">{{ $line['date'] }}</td>
                    <td class="px-4 py-3">{{ $line['type'] }}</td>
                    <td class="px-4 py-3">{{ $line['reference'] }}</td>
                    <td class="px-4 py-3">{{ number_format($line['debit'], 2) }}</td>
                    <td class="px-4 py-3">{{ number_format($line['credit'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="5">No movements in period.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
