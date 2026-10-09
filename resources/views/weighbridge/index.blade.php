<x-layouts.authenticated title="Weighbridge">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Weighment register</h2>
        <p class="mt-1 text-sm text-muted">Gross, tare, and server-calculated net weight.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canConfigure(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('weighments.tolerance', $branches->first()) }}" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <select class="field w-auto" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)
                    <option value="{{ route('weighments.tolerance', $branch) }}">{{ $branch->name }}@if(isset($tolerances[$branch->id])) ({{ $tolerances[$branch->id]->limit_kg }} kg)@endif</option>
                @endforeach
            </select>
            <input name="limit_kg" type="number" min="0" step="0.001" required placeholder="Tolerance kg" class="field w-auto">
            <button class="btn-ghost" type="submit">Save tolerance</button>
        </form>
    @endif
    @if ($desk->canRecord(auth()->user()) && $entries->isNotEmpty())
        <form method="POST" action="{{ route('weighments.store', $entries->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">
                @foreach ($entries as $entry)
                    <option value="{{ route('weighments.store', $entry) }}">{{ $entry->vehicle_no }} · {{ $entry->purpose }}</option>
                @endforeach
            </select>
            <select name="kind" class="field"><option value="gross">Gross</option><option value="tare">Tare</option></select>
            <input name="weight" type="number" min="0" step="0.001" required placeholder="Weight" class="field">
            <label class="text-sm md:col-span-2"><input type="checkbox" name="manual" value="1"> Indicator offline / manual</label>
            <input name="reason" placeholder="Manual reason" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record weighment</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Ticket</th><th class="px-4 py-3">Vehicle</th><th class="px-4 py-3">Gross</th><th class="px-4 py-3">Tare</th><th class="px-4 py-3">Net</th><th class="px-4 py-3">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="px-4 py-3"><a class="font-medium" href="{{ route('weighments.show', $ticket) }}">{{ $ticket->number ?? '#'.$ticket->id }}</a></td>
                        <td class="px-4 py-3">{{ $ticket->vehicle_no ?? $ticket->gateEntry?->vehicle_no }}</td>
                        <td class="px-4 py-3">{{ $ticket->gross_weight ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $ticket->tare_weight ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $ticket->net_weight ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $ticket->status }}@if($ticket->tolerance_status !== 'ok') · {{ $ticket->tolerance_status }}@endif</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No weighments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
