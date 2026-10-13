<x-layouts.authenticated title="Payroll">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Payroll</h2>
        <p class="mt-1 text-sm text-muted">Accountant executes from approved wage sheets; Manager finalizes. No tax rules configured.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canExecutePayroll($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('labor.payroll.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/labor/payroll'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Approved wage sheet
                <select name="labor_wage_sheet_id" required class="field mt-1">
                    @foreach ($sheets as $s)<option value="{{ $s->id }}">#{{ $s->id }} {{ optional($s->period_start)->toDateString() }} ({{ number_format((float)$s->gross_amount,2) }})</option>@endforeach
                </select>
            </label>
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Execute payroll</button></div>
        </form>
    @endif
    @foreach ($runs as $run)
        <div class="mb-3 rounded-2xl border border-line bg-panel p-4 text-sm">
            <div class="flex flex-wrap justify-between gap-2">
                <strong>{{ optional($run->period_start)->toDateString() }} – {{ optional($run->period_end)->toDateString() }}</strong>
                <span>{{ $run->status }}</span>
            </div>
            <p>Gross {{ number_format((float)$run->gross_amount,2) }} − advances {{ number_format((float)$run->advances_deducted,2) }} = net {{ number_format((float)$run->net_payable,2) }}</p>
            <p class="text-muted">{{ $run->calculation_notes }}</p>
            @if ($desk->canApprovePayroll($user) && $run->status === 'pending')
                <form method="POST" action="{{ route('labor.payroll.decide', $run) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Finalize</button></form>
            @endif
        </div>
    @endforeach
    @if ($runs->isEmpty())<p class="text-sm text-muted">No payroll runs.</p>@endif
</x-layouts.authenticated>
