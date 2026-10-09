<x-layouts.authenticated title="Manual temperature log">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Manual temperature log</h2>
        <p class="mt-1 text-sm text-muted">Authorized manual entry when live sensors are offline or unavailable.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canManualLog(auth()->user()) && $chambers->isNotEmpty())
        <form method="POST" action="{{ route('monitoring.store') }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="chamber_id" required class="field">
                @foreach ($chambers as $chamber)
                    <option value="{{ $chamber->id }}">{{ $chamber->code }} · {{ $chamber->name }}</option>
                @endforeach
            </select>
            <select name="sensor_id" class="field">
                <option value="">No sensor</option>
                @foreach ($sensors as $sensor)
                    <option value="{{ $sensor->id }}">{{ $sensor->code }}</option>
                @endforeach
            </select>
            <input name="temperature" type="number" step="0.01" required placeholder="Temperature" class="field">
            <input name="humidity" type="number" step="0.01" placeholder="Humidity" class="field">
            <input name="notes" placeholder="Notes" class="field md:col-span-2">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Save log</button></div>
        </form>
    @else
        <p class="text-sm text-muted">You are not authorized to enter manual temperature logs.</p>
    @endif
</x-layouts.authenticated>
