<x-layouts.authenticated title="SOP Library">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-6">SOP document library</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canManageSop($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.sops.store', $branches->first()) }}" enctype="multipart/form-data" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/sops'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Update existing<select name="quality_sop_document_id" class="field mt-1"><option value="">New document</option>@foreach($documents as $d)<option value="{{ $d->id }}">{{ $d->title }}</option>@endforeach</select></label>
            <label class="text-sm">Title<input name="title" required class="field mt-1"></label>
            <label class="text-sm">Category<input name="category" class="field mt-1"></label>
            <label class="text-sm">Effective<input type="date" name="effective_on" class="field mt-1"></label>
            <label class="text-sm">Review<input type="date" name="review_on" class="field mt-1"></label>
            <label class="text-sm">Attachment<input type="file" name="attachment" class="field mt-1"></label>
            <button class="btn-primary" type="submit">Save SOP</button>
        </form>
    @endif
    <div class="space-y-3">
        @foreach($documents as $d)
            <div class="rounded-2xl border border-line bg-panel p-4 text-sm">
                <strong>{{ $d->title }}</strong> <span class="text-muted">v{{ $d->current_version }} · {{ $d->status }}</span>
                <ul class="mt-2 space-y-1">
                    @foreach($d->versions as $v)
                        <li>v{{ $v->version_number }} @if($v->is_current)<em>(current)</em>@endif
                            @if($v->attachment_path)<a class="underline" href="{{ route('quality.sops.download', $v) }}">{{ $v->attachment_name ?: 'download' }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
