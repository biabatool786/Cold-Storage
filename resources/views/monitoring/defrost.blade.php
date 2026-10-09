<x-layouts.authenticated title="Defrost logs">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Defrost cycle logs</h2>
        <p class="mt-1 text-sm text-muted">Record defrost windows against chambers.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canDefrost(auth()->user()) && $chambers->isNotEmpty())
        <form method="POST" action="{{ route('monitoring.defrost.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="chamber_id" required class="field">
                @foreach ($chambers as $chamber)
                    <option value="{{ $chamber->id }}">{{ $chamber->code }}</option>
                @endforeach
            </select>
            <input name="started_at" type="datetime-local" required class="field">
            <input name="ended_at" type="datetime-local" class="field">
            <input name="notes" placeholder="Notes" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Log defrost</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Chamber</th><th class="px-4 py-3">Started</th><th class="px-4 py-3">Ended</th><th class="px-4 py-3">Notes</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3">{{ $log->chamber?->code }}</td>
                        <td class="px-4 py-3">{{ $log->started_at }}</td>
                        <td class="px-4 py-3">{{ $log->ended_at ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->notes }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No defrost logs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
