<x-layouts.authenticated title="Message Templates">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Message templates</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <p class="text-xs text-muted mb-3">Placeholders:
        @foreach($placeholders as $p)
            <code class="mr-1">@php echo '{{'.$p.'}}'; @endphp</code>
        @endforeach
    </p>
    @if ($desk->canCreateTemplate($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('notifications.templates.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/notifications/templates'">
                    @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Name<input name="name" required class="field mt-1"></label>
            <label class="text-sm">Event/type<input name="event_type" required class="field mt-1" placeholder="invoice_overdue"></label>
            <label class="text-sm">Channel<select name="channel" class="field mt-1"><option>email</option><option>sms</option><option>whatsapp</option><option>in_app</option></select></label>
            <label class="text-sm md:col-span-2">Subject<input name="subject" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Body<textarea name="body" required class="field mt-1" rows="4"></textarea></label>
            <button class="btn-primary" type="submit">Create template</button>
        </form>
    @endif
    <div class="space-y-4">
        @foreach($templates as $t)
            <div class="rounded-2xl border border-line bg-panel p-5">
                <div class="flex flex-wrap justify-between gap-2"><strong>{{ $t->name }}</strong><span class="text-sm text-muted">{{ $t->channel }} · {{ $t->event_type }} · {{ $t->is_active ? 'active' : 'inactive' }}</span></div>
                <a class="text-sm underline" href="{{ route('notifications.templates.preview', $t) }}">Preview</a>
                @if($desk->canEditTemplate($user))
                    <form method="POST" action="{{ route('notifications.templates.update', $t) }}" class="mt-3 grid gap-2 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="name" value="{{ $t->name }}">
                        <input type="hidden" name="event_type" value="{{ $t->event_type }}">
                        <input type="hidden" name="channel" value="{{ $t->channel }}">
                        <label class="text-sm md:col-span-2">Subject<input name="subject" class="field mt-1" value="{{ $t->subject }}"></label>
                        <label class="text-sm md:col-span-2">Body<textarea name="body" required class="field mt-1" rows="3">{{ $t->body }}</textarea></label>
                        <label class="text-sm flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> Active</label>
                        <button class="btn-secondary" type="submit">Save</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
