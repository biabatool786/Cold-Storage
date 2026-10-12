<x-layouts.authenticated title="Receivable aging">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Receivable aging</h2>
            <p class="mt-1 text-sm text-muted">Buckets by due date: current, 1–30, 31–60, 61–90, 90+ days.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-ghost" href="{{ route('payments.index') }}">Payments</a>
            @if ($desk->canPrint(auth()->user()))
                <a class="btn-primary" href="{{ route('payments.aging.print') }}" target="_blank">Print / export</a>
            @endif
        </div>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm text-left">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Customer</th><th class="px-4 py-3">Current</th><th class="px-4 py-3">1–30</th>
                <th class="px-4 py-3">31–60</th><th class="px-4 py-3">61–90</th><th class="px-4 py-3">90+</th><th class="px-4 py-3">Total</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row['customer'] }}</td>
                        <td class="px-4 py-3">{{ number_format($row['current'], 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($row['d30'], 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($row['d60'], 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($row['d90'], 2) }}</td>
                        <td class="px-4 py-3">{{ number_format($row['d90_plus'], 2) }}</td>
                        <td class="px-4 py-3 font-medium">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="7">No outstanding receivables.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
