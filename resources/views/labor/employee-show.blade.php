<x-layouts.authenticated title="{{ $employee->name }}">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $employee->name }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $employee->employee_code }} · {{ $employee->engagement_type }} · {{ $employee->status }}</p>
        </div>
        <a class="btn-ghost" href="{{ route('labor.employees') }}">Back</a>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($showWages)
        <h3 class="mb-2 text-sm font-semibold uppercase text-muted">Wage rates</h3>
        @if ($desk->canProposeWageRate($user) || $user->isSuperAdmin())
            <form method="POST" action="{{ route('labor.wage-rates.store', $employee) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-4 md:grid-cols-4">
                @csrf
                <label class="text-sm">Amount<input name="rate_amount" type="number" step="0.01" required class="field mt-1"></label>
                <label class="text-sm">Unit<input name="rate_unit" class="field mt-1" value="day"></label>
                <label class="text-sm">Effective<input name="effective_from" type="date" class="field mt-1" value="{{ now()->toDateString() }}"></label>
                <label class="text-sm">Reason<input name="reason" class="field mt-1"></label>
                <div class="md:col-span-4"><button class="btn-primary" type="submit">Propose / set rate</button></div>
            </form>
        @endif
        <ul class="mb-6 space-y-2 text-sm">
            @foreach ($employee->wageRates as $rate)
                <li class="rounded-xl border border-line px-3 py-2 flex flex-wrap justify-between gap-2">
                    <span>{{ $rate->rate_amount }}/{{ $rate->rate_unit }} from {{ optional($rate->effective_from)->toDateString() }} — {{ $rate->status }}</span>
                    @if ($desk->canApproveWageRate($user) && $rate->status === 'proposed')
                        <span>
                            <form method="POST" action="{{ route('labor.wage-rates.decide', $rate) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost" type="submit">Approve</button></form>
                            <form method="POST" action="{{ route('labor.wage-rates.decide', $rate) }}" class="inline">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.authenticated>
