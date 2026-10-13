<x-layouts.authenticated title="Leave">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Leave management</h2>
        <p class="mt-1 text-sm text-muted">Request-only for Supervisor/Gatekeeper. Leave entitlements are not configured.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canRequestLeave($user))
        <form method="POST" action="{{ route('labor.leave.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Employee<select name="labor_employee_id" required class="field mt-1">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }}</option>@endforeach</select></label>
            <label class="text-sm">From<input name="starts_on" type="date" required class="field mt-1"></label>
            <label class="text-sm">To<input name="ends_on" type="date" required class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Reason<input name="reason" class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Request leave</button></div>
        </form>
    @endif
    <ul class="space-y-2 text-sm">
        @forelse ($rows as $r)
            <li class="rounded-xl border border-line px-3 py-2 flex flex-wrap justify-between gap-2">
                <span>{{ $r->employee?->employee_code }} {{ optional($r->starts_on)->toDateString() }}–{{ optional($r->ends_on)->toDateString() }} — {{ $r->status }}</span>
                @if ($desk->canApproveLeave($user) && $r->status === 'requested')
                    <span>
                        <form method="POST" action="{{ route('labor.leave.decide', $r) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('labor.leave.decide', $r) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                    </span>
                @endif
            </li>
        @empty<li class="text-muted">No leave requests.</li>@endforelse
    </ul>
</x-layouts.authenticated>
