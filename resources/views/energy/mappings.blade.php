<x-layouts.authenticated title="Meter-to-chamber mapping">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Meter-to-chamber mapping</h2>
        <p class="mt-1 text-sm text-muted">Super Admin and Admin only. Changes keep effective-date history.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($desk->canMapMeters($user))
        @if ($meters->isEmpty() || $chambers->isEmpty())
            <p class="mb-6 rounded-2xl border border-line bg-panel p-5 text-sm text-muted">
                @if ($meters->isEmpty()) Create an electricity meter under Meter Readings first. @endif
                @if ($chambers->isEmpty()) No chambers are available for this tenant/branch. @endif
            </p>
        @else
            <form method="POST" action="{{ route('energy.mappings.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
                @csrf
                <label class="text-sm">Meter
                    <select name="electricity_meter_id" required class="field mt-1">
                        @foreach ($meters as $m)<option value="{{ $m->id }}">{{ $m->meter_code }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Chamber
                    <select name="chamber_id" required class="field mt-1">
                        @foreach ($chambers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Effective from<input name="effective_from" type="date" required class="field mt-1" value="{{ now()->toDateString() }}"></label>
                <div class="md:col-span-3"><button class="btn-primary" type="submit">Save mapping</button></div>
            </form>
        @endif
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Meter</th><th class="px-4 py-3">Chamber</th><th class="px-4 py-3">From</th><th class="px-4 py-3">To</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($mappings as $map)
                    <tr>
                        <td class="px-4 py-3">{{ $map->meter?->meter_code }}</td>
                        <td class="px-4 py-3">{{ $map->chamber?->name }}</td>
                        <td class="px-4 py-3">{{ optional($map->effective_from)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ optional($map->effective_to)->toDateString() ?: 'current' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No mappings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
