<x-layouts.authenticated title="Insurance Policies">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Insurance policy register</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canManageInsurancePolicies($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.policies.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/policies'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Insurer<input name="insurer" required class="field mt-1"></label>
            <label class="text-sm">Policy #<input name="policy_number" required class="field mt-1"></label>
            <label class="text-sm">Coverage type<input name="coverage_type" class="field mt-1"></label>
            <label class="text-sm">Limit<input name="coverage_limit" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm">Premium<input name="premium_amount" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm">Starts<input type="date" name="starts_on" class="field mt-1"></label>
            <label class="text-sm">Expires<input type="date" name="expires_on" class="field mt-1"></label>
            <button class="btn-primary" type="submit">Save policy</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($policies as $p)<li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $p->insurer }} {{ $p->policy_number }} — {{ $p->expiryStatus() }}@if($desk->canManageInsurancePolicies($user)) · limit {{ $p->coverage_limit }} · premium {{ $p->premium_amount }}@endif</li>@endforeach</ul>
</x-layouts.authenticated>
