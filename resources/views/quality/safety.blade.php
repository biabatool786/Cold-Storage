<x-layouts.authenticated title="Gas & Fire Safety">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Gas leak & fire safety equipment logs</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.safety.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/safety'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Type<select name="log_type" class="field mt-1"><option value="gas_leak">Gas leak check</option><option value="fire_safety">Fire safety</option></select></label>
            <label class="text-sm">Checked<input type="date" name="checked_on" class="field mt-1" value="{{ now()->toDateString() }}"></label>
            <label class="text-sm">Next due<input type="date" name="next_due_on" class="field mt-1"></label>
            <label class="text-sm">Equipment<input name="equipment_label" class="field mt-1"></label>
            <label class="text-sm">Result<select name="result" class="field mt-1"><option value="pass">Pass</option><option value="fail">Fail</option></select></label>
            <label class="text-sm md:col-span-2">Findings<textarea name="findings" class="field mt-1" rows="2"></textarea></label>
            <button class="btn-primary" type="submit">Record</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($logs as $l)<li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $l->log_type }} — {{ $l->checked_on?->toDateString() }} — {{ $l->result }} @if($l->result==='fail')<span class="text-red-700">failed</span>@endif @if($l->isOverdue())<span class="text-red-700">overdue</span>@endif</li>@endforeach</ul>
</x-layouts.authenticated>
