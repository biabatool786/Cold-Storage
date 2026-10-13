<x-layouts.authenticated title="Staff Announcements">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Staff announcements</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($branches->isNotEmpty())
        <form method="POST" action="{{ route('notifications.announcements.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/notifications/announcements'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Title<input name="title" required class="field mt-1"></label>
            <label class="text-sm">Message<textarea name="message" required class="field mt-1" rows="4"></textarea></label>
            <div class="text-sm">Target roles:
                @foreach(['Admin','Manager','Supervisor','Gatekeeper','Accountant'] as $r)
                    <label class="mr-3"><input type="checkbox" name="target_roles[]" value="{{ $r }}" checked> {{ $r }}</label>
                @endforeach
            </div>
            <button class="btn-primary" type="submit">Publish</button>
        </form>
    @endif
    <ul class="space-y-2 text-sm">@foreach($announcements as $a)<li class="rounded-xl border border-line bg-panel px-4 py-3"><strong>{{ $a->title }}</strong> — {{ $a->status }} — {{ $a->published_at }}</li>@endforeach</ul>
</x-layouts.authenticated>
