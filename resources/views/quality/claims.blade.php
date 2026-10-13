<x-layouts.authenticated title="Insurance Claims">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Insurance claim files</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.claims.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/claims'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Policy<select name="quality_insurance_policy_id" required class="field mt-1">@foreach($policies as $p)<option value="{{ $p->id }}">{{ $p->policy_number }}</option>@endforeach</select></label>
            <label class="text-sm">Claim ref<input name="claim_reference" required class="field mt-1"></label>
            <label class="text-sm">Incident<select name="quality_incident_id" class="field mt-1"><option value="">—</option>@foreach($incidents as $i)<option value="{{ $i->id }}">#{{ $i->id }}</option>@endforeach</select></label>
            <label class="text-sm">Event date<input type="date" name="event_date" class="field mt-1"></label>
            <label class="text-sm">Amount<input type="number" step="0.01" name="claim_amount" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Description<textarea name="description" class="field mt-1" rows="2"></textarea></label>
            <button class="btn-primary" type="submit">Create claim</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($claims as $c)<li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $c->claim_reference }} — {{ $c->policy?->policy_number }} — {{ $c->status }} — {{ $c->claim_amount }}</li>@endforeach</ul>
</x-layouts.authenticated>
