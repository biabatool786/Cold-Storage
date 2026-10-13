<x-layouts.authenticated title="NCR & Corrective Actions">
    @php $user = auth()->user(); @endphp
    <h2 class="text-2xl font-semibold tracking-tight mb-1">NCR & corrective actions</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        @if ($desk->canRaiseNcr($user) && $branches->isNotEmpty())
            <form method="POST" action="{{ route('quality.ncrs.store', $branches->first()) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <h3 class="font-medium">Raise NCR</h3>
                <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/ncrs'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">Reference<input name="reference_number" class="field mt-1" placeholder="Auto if blank"></label>
                <label class="text-sm">Failed inspection<select name="quality_inspection_id" class="field mt-1"><option value="">—</option>@foreach($inspections as $i)<option value="{{ $i->id }}">#{{ $i->id }} {{ $i->category }}</option>@endforeach</select></label>
                <label class="text-sm">Severity<select name="severity" class="field mt-1"><option>medium</option><option>low</option><option>high</option><option>critical</option></select></label>
                <label class="text-sm">Description<textarea name="description" required class="field mt-1" rows="3"></textarea></label>
                <button class="btn-primary" type="submit">Raise NCR</button>
            </form>
        @endif
        @if ($desk->canAssignCorrective($user) && $branches->isNotEmpty())
            <form method="POST" action="{{ route('quality.corrective.store', $branches->first()) }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <h3 class="font-medium">Assign corrective action</h3>
                <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/corrective'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
                <label class="text-sm">NCR<select name="quality_ncr_id" class="field mt-1"><option value="">—</option>@foreach($ncrs as $n)<option value="{{ $n->id }}">{{ $n->reference_number }}</option>@endforeach</select></label>
                <label class="text-sm">Responsible<select name="responsible_user_id" class="field mt-1"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></label>
                <label class="text-sm">Due date<input type="date" name="due_date" class="field mt-1"></label>
                <label class="text-sm">Description<textarea name="description" required class="field mt-1" rows="3"></textarea></label>
                <button class="btn-primary" type="submit">Assign</button>
            </form>
        @endif
    </div>
    <div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">Ref</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach($ncrs as $n)
                    <tr>
                        <td class="px-4 py-3">{{ $n->reference_number }}<div class="text-xs text-muted">{{ Str::limit($n->description, 80) }}</div></td>
                        <td class="px-4 py-3">{{ $n->status }}</td>
                        <td class="px-4 py-3 space-y-2">
                            @if(in_array($n->status, ['open','corrective_assigned']))
                                <form method="POST" action="{{ route('quality.ncrs.request-closure', $n) }}" class="flex gap-2">@csrf<input name="closure_evidence" required class="field" placeholder="Evidence"><button class="btn-secondary" type="submit">Request close</button></form>
                            @endif
                            @if($n->status==='pending_closure' && $desk->canCloseNcr($user))
                                <form method="POST" action="{{ route('quality.ncrs.decide', $n) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve close</button></form>
                                <form method="POST" action="{{ route('quality.ncrs.decide', $n) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-secondary" type="submit">Reject</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <h3 class="mt-8 font-medium">Corrective actions</h3>
    <ul class="mt-2 space-y-2 text-sm">
        @foreach($actions as $a)
            <li class="rounded-xl border border-line bg-panel px-4 py-3">{{ $a->description }} — {{ $a->status }}@if($a->isOverdue()) <span class="text-red-700">overdue</span>@endif due {{ $a->due_date }}</li>
        @endforeach
    </ul>
</x-layouts.authenticated>
