<x-layouts.authenticated title="Certificates">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Certificates & licenses</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canManageCertificates($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.certificates.store', $branches->first()) }}" enctype="multipart/form-data" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/certificates'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Type<input name="document_type" required class="field mt-1"></label>
            <label class="text-sm">Reference<input name="reference_number" required class="field mt-1"></label>
            <label class="text-sm">Issuer<input name="issuer" class="field mt-1"></label>
            <label class="text-sm">Issued<input type="date" name="issued_on" class="field mt-1"></label>
            <label class="text-sm">Expires<input type="date" name="expires_on" class="field mt-1"></label>
            <label class="text-sm">Attachment<input type="file" name="attachment" class="field mt-1"></label>
            <button class="btn-primary" type="submit">Save</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($certificates as $c)<li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $c->document_type }} {{ $c->reference_number }} — <strong>{{ $c->expiryStatus() }}</strong> @if($c->expires_on)(expires {{ $c->expires_on->toDateString() }})@endif</li>@endforeach</ul>
</x-layouts.authenticated>
