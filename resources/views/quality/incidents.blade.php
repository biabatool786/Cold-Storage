<x-layouts.authenticated title="Incidents">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Incidents & accidents</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canReportIncident($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('quality.incidents.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/incidents'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">When<input type="datetime-local" name="occurred_at" class="field mt-1"></label>
            <label class="text-sm">Location<input name="location" class="field mt-1"></label>
            <label class="text-sm">Category<input name="category" class="field mt-1"></label>
            <label class="text-sm md:col-span-2">Description<textarea name="description" required class="field mt-1" rows="3"></textarea></label>
            <label class="text-sm md:col-span-2">Immediate actions<textarea name="immediate_actions" class="field mt-1" rows="2"></textarea></label>
            <button class="btn-primary" type="submit">Report</button>
        </form>
    @endif
    <div class="space-y-3">
        @foreach($incidents as $i)
            <div class="rounded-2xl border border-line bg-panel p-4 text-sm">
                <div class="flex justify-between"><strong>#{{ $i->id }} {{ $i->category }}</strong><span>{{ $i->status }}</span></div>
                <p class="mt-1">{{ $i->description }}</p>
                @if($i->status==='open')
                    <form method="POST" action="{{ route('quality.incidents.request-closure', $i) }}" class="mt-2 flex gap-2">@csrf<input name="investigation_notes" class="field" placeholder="Investigation notes"><button class="btn-secondary" type="submit">Request close</button></form>
                @endif
                @if($i->status==='pending_closure' && $desk->canCloseIncident($user))
                    <form method="POST" action="{{ route('quality.incidents.decide', $i) }}" class="inline mt-2">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve close</button></form>
                    <form method="POST" action="{{ route('quality.incidents.decide', $i) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-secondary" type="submit">Reject</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
