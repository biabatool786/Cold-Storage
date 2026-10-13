<x-layouts.authenticated title="Shifts">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Shift scheduling</h2>
        <p class="mt-1 text-sm text-muted">Gatekeeper may schedule own team only.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('labor.shifts.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/labor/shifts'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Name<input name="name" required class="field mt-1"></label>
            <label class="text-sm">Date<input name="shift_date" type="date" required class="field mt-1"></label>
            <label class="text-sm">Start<input name="starts_at" type="time" required class="field mt-1"></label>
            <label class="text-sm">End<input name="ends_at" type="time" required class="field mt-1"></label>
            <label class="text-sm">Team code<input name="team_code" class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Employees
                <select name="employee_ids[]" multiple class="field mt-1" size="4">
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }} — {{ $e->name }} ({{ $e->team_code }})</option>@endforeach
                </select>
            </label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Create shift</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">Shift</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">Time</th><th class="px-4 py-3">Team</th><th class="px-4 py-3">Assigned</th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($shifts as $s)
                    <tr>
                        <td class="px-4 py-3">{{ $s->name }}</td>
                        <td class="px-4 py-3">{{ optional($s->shift_date)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $s->starts_at }}–{{ $s->ends_at }}</td>
                        <td class="px-4 py-3">{{ $s->team_code ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $s->employees->pluck('employee_code')->join(', ') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No shifts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
