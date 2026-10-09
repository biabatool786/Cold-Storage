<x-layouts.authenticated title="Sensor calibration">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Sensor calibration records</h2>
        <p class="mt-1 text-sm text-muted">SA/Admin create; Manager may view.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCalibrate(auth()->user()) && $sensors->isNotEmpty())
        <form method="POST" action="{{ route('monitoring.calibrations.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="sensor_id" required class="field">
                @foreach ($sensors as $sensor)
                    <option value="{{ $sensor->id }}">{{ $sensor->code }}</option>
                @endforeach
            </select>
            <input name="calibrated_at" type="datetime-local" required class="field">
            <input name="reference_value" type="number" step="0.01" required placeholder="Reference" class="field">
            <input name="measured_value" type="number" step="0.01" required placeholder="Measured" class="field">
            <input name="notes" placeholder="Notes" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Save calibration</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr>
                <th class="px-4 py-3">Sensor</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3">Measured</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($calibrations as $row)
                    <tr>
                        <td class="px-4 py-3">{{ $row->sensor?->code }}</td>
                        <td class="px-4 py-3">{{ $row->calibrated_at }}</td>
                        <td class="px-4 py-3">{{ $row->reference_value }}</td>
                        <td class="px-4 py-3">{{ $row->measured_value }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No calibrations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
