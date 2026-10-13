<x-layouts.authenticated title="Overtime">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Overtime</h2>
        <p class="mt-1 text-sm text-muted">No overtime multiplier is configured; hours are recorded for approval only.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canEnterOvertime($user))
        <form method="POST" action="{{ route('labor.overtime.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Employee<select name="labor_employee_id" required class="field mt-1">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }}</option>@endforeach</select></label>
            <label class="text-sm">Date<input name="overtime_date" type="date" required class="field mt-1"></label>
            <label class="text-sm">Hours<input name="hours" type="number" step="0.01" min="0.01" required class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Reason<input name="reason" class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Enter overtime</button></div>
        </form>
    @endif
    <ul class="space-y-2 text-sm">
        @forelse ($rows as $r)
            <li class="rounded-xl border border-line px-3 py-2 flex flex-wrap justify-between gap-2">
                <span>{{ optional($r->overtime_date)->toDateString() }} {{ $r->employee?->employee_code }} — {{ $r->hours }}h — {{ $r->status }}</span>
                @if ($desk->canApproveOvertime($user) && $r->status === 'pending')
                    <span>
                        <form method="POST" action="{{ route('labor.overtime.decide', $r) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('labor.overtime.decide', $r) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                    </span>
                @endif
            </li>
        @empty<li class="text-muted">No overtime.</li>@endforelse
    </ul>
</x-layouts.authenticated>
