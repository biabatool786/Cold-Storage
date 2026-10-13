<x-layouts.authenticated title="Inspections">
    <h2 class="text-2xl font-semibold tracking-tight mb-1">Perform inspections</h2>
    <p class="text-sm text-muted mb-6">Uses the current template version snapshot at submission time.</p>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($branches->isNotEmpty() && $templates->isNotEmpty())
        <form method="POST" action="{{ route('quality.inspections.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5" id="insp-form">
            @csrf
            <label class="text-sm">Branch<select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/quality/inspections'">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></label>
            <label class="text-sm">Template
                <select name="quality_checklist_template_id" id="tpl" required class="field mt-1">
                    @foreach($templates as $t)<option value="{{ $t->id }}" data-items='@json($t->latestVersion?->items ?? [])'>{{ $t->name }} ({{ $t->category }} v{{ $t->current_version }})</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Lot<select name="storage_lot_id" class="field mt-1"><option value="">—</option>@foreach($lots as $l)<option value="{{ $l->id }}">{{ $l->lot_number }}</option>@endforeach</select></label>
            <div id="items" class="space-y-2"></div>
            <label class="text-sm">Notes<textarea name="notes" class="field mt-1" rows="2"></textarea></label>
            <button class="btn-primary" type="submit">Submit inspection</button>
        </form>
        <script>
            function renderItems(){const sel=document.getElementById('tpl');const box=document.getElementById('items');const items=JSON.parse(sel.selectedOptions[0].dataset.items||'[]');box.innerHTML=items.map((it,i)=>`<label class="text-sm block border border-line rounded-xl p-3">${it.label}${it.required?' *':''}<select name="responses[${i}][response]" class="field mt-1" ${it.required?'required':''}><option value="">—</option><option value="pass">Pass</option><option value="fail">Fail</option><option value="na">N/A</option></select><input name="responses[${i}][notes]" class="field mt-1" placeholder="Notes"></label>`).join('');}
            document.getElementById('tpl').addEventListener('change',renderItems);renderItems();
        </script>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm"><thead class="text-xs uppercase text-muted"><tr><th class="px-4 py-3">ID</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Result</th><th class="px-4 py-3">When</th></tr></thead>
            <tbody class="divide-y divide-line">@forelse($inspections as $i)<tr><td class="px-4 py-3">#{{ $i->id }}</td><td class="px-4 py-3">{{ $i->category }}</td><td class="px-4 py-3">{{ $i->overall_result }}</td><td class="px-4 py-3">{{ $i->inspected_at }}</td></tr>@empty<tr><td class="px-4 py-6 text-muted" colspan="4">No inspections.</td></tr>@endforelse</tbody>
        </table>
    </div>
</x-layouts.authenticated>
