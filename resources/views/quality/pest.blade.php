<x-layouts.authenticated title="Pest & Sanitation">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Pest control & sanitation logs</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.pest.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/pest'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Type<select name="log_type" class="field mt-1"><option value="pest_control">Pest control</option><option value="sanitation">Sanitation</option></select></label>
            <label class="text-sm">Date<input type="date" name="logged_on" class="field mt-1" value="{{ now()->toDateString() }}"></label>
            <label class="text-sm">Follow-up<input type="date" name="follow_up_on" class="field mt-1"></label>
            <label class="text-sm">Location<input name="location" class="field mt-1"></label>
            <label class="text-sm">Provider<input name="service_provider" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Findings<textarea name="findings" class="field mt-1" rows="2"></textarea></label>
            <label class="text-sm md:col-span-2">Actions<textarea name="actions_taken" class="field mt-1" rows="2"></textarea></label>
            <button class="btn-primary" type="submit">Record log</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($logs as $l)<li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $l->log_type }} — {{ $l->logged_on?->toDateString() }} — {{ $l->location }} @if($l->isFollowUpOverdue())<span class="text-red-700">follow-up overdue</span>@endif</li>@endforeach</ul>
</x-layouts.authenticated>
