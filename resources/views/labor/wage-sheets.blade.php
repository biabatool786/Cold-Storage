<x-layouts.authenticated title="Wage sheets">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Wage sheets</h2>
        <p class="mt-1 text-sm text-muted">From approved rates × present days. Self-approval blocked.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canRecordWork($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('labor.wage-sheets.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/labor/wage-sheets'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">From<input name="period_start" type="date" required class="field mt-1"></label>
            <label class="text-sm">To<input name="period_end" type="date" required class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Generate</button></div>
        </form>
    @endif
    @foreach ($sheets as $sheet)
        <div class="mb-4 rounded-2xl border border-line bg-panel p-5 text-sm">
            <div class="flex flex-wrap justify-between gap-2">
                <strong>{{ optional($sheet->period_start)->toDateString() }} – {{ optional($sheet->period_end)->toDateString() }}</strong>
                <span>{{ $sheet->status }} · {{ number_format((float)$sheet->gross_amount, 2) }}</span>
            </div>
            <p class="mt-1 text-muted">{{ $sheet->calculation_notes }}</p>
            <ul class="mt-2 space-y-1">
                @foreach ($sheet->lines as $line)
                    <li>{{ $line->employee?->employee_code }}: {{ $line->source_notes }} = {{ number_format((float)$line->line_amount, 2) }}</li>
                @endforeach
            </ul>
            @if ($desk->canApproveWageSheet($user) && $sheet->status === 'pending')
                <form method="POST" action="{{ route('labor.wage-sheets.decide', $sheet) }}" class="mt-2 inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                <form method="POST" action="{{ route('labor.wage-sheets.decide', $sheet) }}" class="mt-2 inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
            @endif
        </div>
    @endforeach
    @if ($sheets->isEmpty())<p class="text-sm text-muted">No wage sheets.</p>@endif
</x-layouts.authenticated>
