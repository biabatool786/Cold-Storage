<x-layouts.authenticated title="Electricity meter readings">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Electricity meter readings</h2>
            <p class="mt-1 text-sm text-muted">Manual readings with consumption and auditable resets.</p>
        </div>
        @if ($desk->canViewReports($user))
            <a class="btn-ghost" href="{{ route('energy.reports') }}">Reports</a>
        @endif
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    @if ($desk->canEnterReading($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('energy.meters.store', $branches->first()) }}" class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <label class="text-sm">Branch
                <select name="branch_picker" class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/energy/meters'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Meter code<input name="meter_code" required class="field mt-1"></label>
            <label class="text-sm">Name<input name="name" class="field mt-1"></label>
            <div class="flex items-end"><button class="btn-primary" type="submit">Add meter</button></div>
        </form>
        @if ($meters->isNotEmpty())
            <form method="POST" action="{{ route('energy.readings.store', $meters->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
                @csrf
                <label class="text-sm md:col-span-3">Meter
                    <select name="meter_picker" class="field mt-1" onchange="this.form.action='{{ url('/energy/meters') }}/'+this.value+'/readings'">
                        @foreach ($meters as $m)<option value="{{ $m->id }}">{{ $m->meter_code }} (last {{ $m->last_reading }})</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">Current reading<input name="current_reading" type="number" step="0.001" min="0" required class="field mt-1"></label>
                <label class="text-sm">Read at<input name="read_at" type="datetime-local" class="field mt-1"></label>
                <label class="text-sm flex items-center gap-2 mt-6"><input type="checkbox" name="is_reset" value="1"> Meter reset / replacement</label>
                <label class="text-sm md:col-span-3">Reset reason <span class="text-muted">(required when reset is checked)</span><input name="reset_reason" class="field mt-1"></label>
                <label class="text-sm md:col-span-3">Notes<textarea name="notes" class="field mt-1" rows="2"></textarea></label>
                <div class="md:col-span-3"><button class="btn-primary" type="submit">Record reading</button></div>
            </form>
        @else
            <p class="mb-6 text-sm text-muted">Add a meter above before recording readings.</p>
        @endif
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Meter</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Prev</th><th class="px-4 py-3">Current</th><th class="px-4 py-3">kWh</th><th class="px-4 py-3">Reset</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($readings as $r)
                    <tr>
                        <td class="px-4 py-3">{{ $r->meter?->meter_code }}</td>
                        <td class="px-4 py-3">{{ $r->read_at }}</td>
                        <td class="px-4 py-3">{{ $r->previous_reading }}</td>
                        <td class="px-4 py-3">{{ $r->current_reading }}</td>
                        <td class="px-4 py-3">{{ $r->consumption }}</td>
                        <td class="px-4 py-3">{{ $r->is_reset ? 'yes' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No readings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
