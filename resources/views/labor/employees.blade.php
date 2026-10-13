<x-layouts.authenticated title="Employees">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Employee / labor master</h2>
            <p class="mt-1 text-sm text-muted">Branch labor register. Wage details restricted by role.</p>
        </div>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canCreateEmployee($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('labor.employees.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/labor/employees'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Code<input name="employee_code" required class="field mt-1"></label>
            <label class="text-sm">Name<input name="name" required class="field mt-1"></label>
            <label class="text-sm">Category<input name="labor_category" class="field mt-1"></label>
            <label class="text-sm">Engagement
                <select name="engagement_type" class="field mt-1">
                    <option value="daily_wage">Daily wage</option><option value="temporary">Temporary</option><option value="permanent">Permanent</option>
                </select>
            </label>
            <label class="text-sm">Team code<input name="team_code" class="field mt-1"></label>
            <label class="text-sm">Supervisor
                <select name="supervisor_id" class="field mt-1"><option value="">—</option>
                    @foreach ($supervisors as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm flex items-center gap-2 mt-6"><input type="checkbox" name="is_gate_staff" value="1"> Gate staff</label>
            <label class="text-sm flex items-center gap-2 mt-6"><input type="checkbox" name="is_visitor_labor" value="1"> Visitor labor</label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Create employee</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Code</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Team</th><th class="px-4 py-3">Status</th>
                @if ($showWages)<th class="px-4 py-3">Rate</th>@endif
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($employees as $e)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('labor.employees.show', $e) }}">{{ $e->employee_code }}</a></td>
                        <td class="px-4 py-3">{{ $e->name }}</td>
                        <td class="px-4 py-3">{{ $e->team_code ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $e->status }}</td>
                        @if ($showWages)<td class="px-4 py-3">{{ optional($e->currentApprovedRate())->rate_amount ?? '—' }}</td>@endif
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No employees.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
