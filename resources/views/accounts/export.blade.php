<x-layouts.authenticated title="Accounting export">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Export to accounting software</h2>
        <p class="mt-1 text-sm text-muted">Reports unavailable unless an integration is configured. No false success is shown.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canExecuteAccounts(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('accounts.export.run', $branches->first()) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <input name="period_start" type="date" required value="{{ now()->startOfMonth()->toDateString() }}" class="field">
            <input name="period_end" type="date" required value="{{ now()->toDateString() }}" class="field">
            <button class="btn-primary" type="submit">Run export</button>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr>
            <th class="px-4 py-3">Period</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Outcome</th>
        </tr></thead><tbody class="divide-y divide-line">
            @forelse ($exports as $export)
                <tr>
                    <td class="px-4 py-3">{{ optional($export->period_start)->toDateString() }} – {{ optional($export->period_end)->toDateString() }}</td>
                    <td class="px-4 py-3">{{ $export->status }}</td>
                    <td class="px-4 py-3">{{ $export->outcome }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-muted" colspan="3">No export attempts.</td></tr>
            @endforelse
        </tbody></table>
    </div>
</x-layouts.authenticated>
