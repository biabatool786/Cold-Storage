<x-layouts.authenticated title="Attendance">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Attendance</h2>
        <p class="mt-1 text-sm text-muted">Gatekeeper may mark gate staff/visitor labor only. Supervisor edits are same-day only.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canMarkAttendance($user))
        <form method="POST" action="{{ route('labor.attendance.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm md:col-span-2">Employee
                <select name="labor_employee_id" required class="field mt-1">
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }} — {{ $e->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Date<input name="attendance_date" type="date" required class="field mt-1" value="{{ now()->toDateString() }}"></label>
            <label class="text-sm">Status
                <select name="status" class="field mt-1"><option value="present">Present</option><option value="absent">Absent</option><option value="half_day">Half day</option></select>
            </label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Mark attendance</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-4 py-3">{{ optional($r->attendance_date)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $r->employee?->employee_code }}</td>
                        <td class="px-4 py-3">{{ $r->status }}</td>
                        <td class="px-4 py-3">
                            @if ($desk->canEditAttendance($user))
                                <form method="POST" action="{{ route('labor.attendance.correct', $r) }}" class="flex flex-wrap gap-1">@csrf
                                    <select name="status" class="field"><option value="present">present</option><option value="absent">absent</option><option value="half_day">half_day</option></select>
                                    <input name="reason" required placeholder="reason" class="field w-32">
                                    <button class="btn-ghost" type="submit">Correct</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No attendance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
