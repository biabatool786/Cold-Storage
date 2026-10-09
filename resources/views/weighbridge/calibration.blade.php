<x-layouts.authenticated title="Calibration">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Weighbridge calibration log</h2>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canCalibrate(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('weighments.calibration.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)<option value="{{ route('weighments.calibration.store', $branch) }}">{{ $branch->name }}</option>@endforeach
            </select>
            <input name="weighbridge_name" value="Main" class="field">
            <input name="calibrated_on" type="date" required class="field">
            <input name="next_due_on" type="date" class="field">
            <select name="result" class="field"><option value="pass">Pass</option><option value="fail">Fail</option></select>
            <input name="certificate_ref" placeholder="Certificate" class="field">
            <input name="notes" placeholder="Notes" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Record calibration</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Bridge</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Result</th><th class="px-4 py-3">Next due</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row->weighbridge_name }}</td>
                        <td class="px-4 py-3">{{ $row->calibrated_on?->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $row->result }} · {{ $row->certificate_ref }}</td>
                        <td class="px-4 py-3">{{ $row->next_due_on?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No calibration records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
