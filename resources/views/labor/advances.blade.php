<x-layouts.authenticated title="Advances">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Labor advances</h2>
        <p class="mt-1 text-sm text-muted">Supervisor requests only; Manager approves within limit.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    @if ($desk->canRequestAdvance($user))
        <form method="POST" action="{{ route('labor.advances.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Employee<select name="labor_employee_id" required class="field mt-1">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }}</option>@endforeach</select></label>
            <label class="text-sm">Amount<input name="amount" type="number" step="0.01" required class="field mt-1"></label>
            <label class="text-sm">Reason<input name="reason" class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Request advance</button></div>
        </form>
    @endif
    <ul class="space-y-2 text-sm">
        @forelse ($rows as $r)
            <li class="rounded-xl border border-line px-3 py-2 flex flex-wrap justify-between gap-2">
                <span>{{ $r->employee?->employee_code }} — {{ number_format((float)$r->amount,2) }} (outstanding {{ number_format((float)$r->outstanding_amount,2) }}) — {{ $r->status }}</span>
                <span>
                    @if ($desk->canApproveAdvance($user) && $r->status === 'requested')
                        <form method="POST" action="{{ route('labor.advances.decide', $r) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost" type="submit">Approve</button></form>
                    @endif
                    @if ($user->hasRole(\App\Enums\RoleName::Accountant) && $r->status === 'approved')
                        <form method="POST" action="{{ route('labor.advances.pay', $r) }}" class="inline">@csrf<button class="btn-ghost" type="submit">Mark paid</button></form>
                    @endif
                </span>
            </li>
        @empty<li class="text-muted">No advances.</li>@endforelse
    </ul>
</x-layouts.authenticated>
