<x-layouts.authenticated title="PPE & safety">
    @php $user = auth()->user(); @endphp
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">PPE &amp; safety training</h2></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canLogPpe($user))
        <form method="POST" action="{{ route('labor.ppe.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Employee<select name="labor_employee_id" required class="field mt-1">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_code }}</option>@endforeach</select></label>
            <label class="text-sm">Item / training<input name="item_or_training" required class="field mt-1"></label>
            <label class="text-sm">Type<select name="log_type" class="field mt-1"><option value="ppe">PPE</option><option value="training">Training</option></select></label>
            <label class="text-sm">Date<input name="logged_on" type="date" class="field mt-1" value="{{ now()->toDateString() }}"></label>
            <label class="text-sm">Qty<input name="quantity" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm">Expires<input name="expires_on" type="date" class="field mt-1"></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Log</button></div>
        </form>
    @endif
    <ul class="space-y-2 text-sm">
        @forelse ($logs as $log)
            <li class="rounded-xl border border-line px-3 py-2">{{ optional($log->logged_on)->toDateString() }} {{ $log->employee?->employee_code }} — {{ $log->item_or_training }}
                @if($log->expires_on && $log->expires_on->isPast()) <span class="text-red-700">(expired)</span>
                @elseif($log->expires_on && $log->expires_on->lte(now()->addDays(30))) <span class="text-muted">(expiring)</span>@endif
            </li>
        @empty<li class="text-muted">No logs.</li>@endforelse
    </ul>
</x-layouts.authenticated>
