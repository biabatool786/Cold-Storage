<x-layouts.authenticated title="Sensors">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Sensors</h2>
        <p class="mt-1 text-sm text-muted">Identify and configure chamber sensors. Status stays offline until a live feed exists.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canCreateSensor(auth()->user()) && $chambers->isNotEmpty())
        <form method="POST" action="{{ route('monitoring.sensors.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="chamber_id" required class="field">
                @foreach ($chambers as $chamber)
                    <option value="{{ $chamber->id }}">{{ $chamber->code }} · {{ $chamber->name }}</option>
                @endforeach
            </select>
            <input name="code" required placeholder="Sensor code" class="field">
            <input name="name" required placeholder="Name" class="field">
            <select name="type" class="field"><option value="temperature">Temperature</option><option value="humidity">Humidity</option><option value="both">Both</option></select>
            <select name="status" class="field"><option value="offline">Offline</option><option value="active">Active</option><option value="maintenance">Maintenance</option></select>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Add sensor</button></div>
        </form>
    @endif
    <div class="space-y-4">
        @foreach ($sensors as $sensor)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
                <p class="font-semibold">{{ $sensor->code }} · {{ $sensor->name }}</p>
                <p class="text-muted">{{ $sensor->chamber?->code }} · {{ $sensor->type }} · {{ $sensor->status }} · last seen {{ $sensor->last_seen_at ?? 'never' }}</p>
                @if ($desk->canConfigureSensor(auth()->user()))
                    <form method="POST" action="{{ route('monitoring.sensors.update', $sensor) }}" class="mt-3 grid gap-2 md:grid-cols-4">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $sensor->name }}" required class="field">
                        <select name="type" class="field">
                            @foreach (['temperature','humidity','both'] as $type)
                                <option value="{{ $type }}" @selected($sensor->type === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="field">
                            @foreach (['active','offline','maintenance','retired'] as $status)
                                <option value="{{ $status }}" @selected($sensor->status === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <button class="btn-ghost" type="submit">Save</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
