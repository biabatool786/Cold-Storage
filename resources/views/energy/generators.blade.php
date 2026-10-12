<x-layouts.authenticated title="Generator logs">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Generator fuel &amp; runtime</h2>
        <p class="mt-1 text-sm text-muted">Manual generator logs. Suspicious values are flagged.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($desk->canEnterGeneratorLog($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('energy.generators.store', $branches->first()) }}" class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/energy/generators'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Code<input name="generator_code" required class="field mt-1"></label>
            <label class="text-sm">Name<input name="name" class="field mt-1"></label>
            <div class="flex items-end"><button class="btn-primary" type="submit">Add generator</button></div>
        </form>
        @if ($generators->isNotEmpty())
            <form method="POST" action="{{ route('energy.generator-logs.store', $generators->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
                @csrf
                <label class="text-sm md:col-span-3">Generator
                    <select class="field mt-1" onchange="this.form.action='{{ url('/energy/generators') }}/'+this.value+'/logs'">
                        @foreach ($generators as $g)<option value="{{ $g->id }}">{{ $g->generator_code }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Runtime (h)<input name="runtime_hours" type="number" step="0.01" min="0" required class="field mt-1"></label>
                <label class="text-sm">Fuel qty<input name="fuel_quantity" type="number" step="0.001" min="0" required class="field mt-1"></label>
                <label class="text-sm">Logged at<input name="logged_at" type="datetime-local" class="field mt-1"></label>
                <label class="text-sm md:col-span-3">Notes<textarea name="notes" class="field mt-1" rows="2"></textarea></label>
                <div class="md:col-span-3"><button class="btn-primary" type="submit">Record log</button></div>
            </form>
        @endif
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Generator</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Hours</th><th class="px-4 py-3">Fuel</th><th class="px-4 py-3">Flag</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3">{{ $log->generator?->generator_code }}</td>
                        <td class="px-4 py-3">{{ $log->logged_at }}</td>
                        <td class="px-4 py-3">{{ $log->runtime_hours }}</td>
                        <td class="px-4 py-3">{{ $log->fuel_quantity }} {{ $log->fuel_unit }}</td>
                        <td class="px-4 py-3">{{ $log->flagged ? ($log->flag_reason ?: 'flagged') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No generator logs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
