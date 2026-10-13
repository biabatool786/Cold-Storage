<x-layouts.authenticated title="Inspection Templates">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-1">Inspection checklist templates</h2>
    <p class="text-sm text-muted mb-6">Edits create a new version; completed inspections keep their snapshot.</p>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.templates.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/templates'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Name<input name="name" required class="field mt-1"></label>
            <label class="text-sm">Category<select name="category" class="field mt-1"><option value="incoming">Incoming</option><option value="periodic">Periodic</option><option value="outgoing">Outgoing</option></select></label>
            <label class="text-sm">Process<input name="applicable_process" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Instructions<textarea name="instructions" class="field mt-1" rows="2"></textarea></label>
            <label class="text-sm md:col-span-2">Items (one per line; prefix with ? for optional)<textarea name="items" required class="field mt-1" rows="4" placeholder="Temperature check&#10;?Visual damage"></textarea></label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create template</button></div>
        </form>
    @endif
    <div class="space-y-4">
        @foreach ($templates as $t)
            <div class="rounded-2xl border border-line bg-panel p-5">
                <div class="flex flex-wrap justify-between gap-2"><strong>{{ $t->name }}</strong><span class="text-sm text-muted">{{ $t->category }} · v{{ $t->current_version }} · {{ $t->is_active ? 'active' : 'inactive' }}</span></div>
                <form method="POST" action="{{ route('quality.templates.update', $t) }}" class="mt-3 grid gap-2 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="name" value="{{ $t->name }}">
                    <input type="hidden" name="category" value="{{ $t->category }}">
                    <label class="text-sm md:col-span-2">New version items<textarea name="items" required class="field mt-1" rows="3">@foreach(($t->latestVersion?->items ?? []) as $item){{ ($item['required'] ?? true) ? '' : '?' }}{{ $item['label'] ?? '' }}&#10;@endforeach</textarea></label>
                    <label class="text-sm flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> Active</label>
                    <button class="btn-secondary" type="submit">Save as new version</button>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
