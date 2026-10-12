<x-layouts.authenticated title="Bulk invoice run">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Bulk invoice run</h2>
        <p class="mt-1 text-sm text-muted">Month/season-end run with duplicate-period protection and Manager approval.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canRequestBulk(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('billing.bulk.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <select class="field" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)
                    <option value="{{ route('billing.bulk.store', $branch) }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <input name="period_start" type="date" required class="field">
            <input name="period_end" type="date" required class="field">
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Submit bulk run</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($runs as $run)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-line bg-panel p-4 text-sm">
                <div>
                    <p class="font-medium">{{ $run->period_start?->format('Y-m-d') }} → {{ $run->period_end?->format('Y-m-d') }} · {{ $run->status }}</p>
                    @if ($run->summary)
                        <p class="text-muted">Created {{ $run->summary['created'] ?? 0 }} · Skipped {{ $run->summary['skipped'] ?? 0 }} · Scanned {{ $run->summary['lots_scanned'] ?? 0 }}</p>
                    @endif
                </div>
                @if ($run->status === 'pending' && $desk->canApproveBulk(auth()->user()))
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('billing.bulk.decide', $run) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('billing.bulk.decide', $run) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
