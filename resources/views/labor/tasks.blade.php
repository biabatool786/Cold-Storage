<x-layouts.authenticated title="Loading tasks">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Loading / unloading &amp; work done</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canAssignLoading($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('labor.tasks.store', $branches->first()) }}" class="mb-4 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/labor/tasks'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Type
                <select name="task_type" class="field mt-1"><option value="unloading">Unloading</option><option value="loading">Loading</option></select>
            </label>
            <label class="text-sm">Reference<input name="reference_label" class="field mt-1" placeholder="GRN/DO label"></label>
            <label class="text-sm md:col-span-3">Workers
                <select name="employee_ids[]" multiple class="field mt-1" size="3">
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }} — {{ $e->name }}</option>@endforeach
                </select>
            </label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Assign task</button></div>
        </form>
    @endif
    @if ($desk->canRecordWork($user))
        <form method="POST" action="{{ route('labor.work.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
            @csrf
            <label class="text-sm">Employee
                <select name="labor_employee_id" required class="field mt-1">
                    @foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Task
                <select name="labor_loading_task_id" class="field mt-1"><option value="">—</option>
                    @foreach ($tasks as $t)<option value="{{ $t->id }}">#{{ $t->id }} {{ $t->task_type }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Qty<input name="quantity" type="number" step="0.001" min="0" required class="field mt-1"></label>
            <label class="text-sm">Unit<input name="unit" class="field mt-1" value="bags"></label>
            <div class="md:col-span-4"><button class="btn-primary" type="submit">Record work</button></div>
        </form>
    @endif
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Tasks</h3>
    <ul class="mb-4 space-y-1 text-sm">@forelse($tasks as $t)<li>#{{ $t->id }} {{ $t->task_type }} — {{ $t->reference_label ?: 'n/a' }} — {{ $t->employees->pluck('employee_code')->join(', ') }}</li>@empty<li class="text-muted">None.</li>@endforelse</ul>
    <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Work</h3>
    <ul class="space-y-1 text-sm">@forelse($work as $w)<li>{{ optional($w->work_date)->toDateString() }} {{ $w->employee?->employee_code }} — {{ $w->quantity }} {{ $w->unit }}</li>@empty<li class="text-muted">None.</li>@endforelse</ul>
</x-layouts.authenticated>
