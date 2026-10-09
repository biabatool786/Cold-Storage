<x-layouts.authenticated title="Gate history">
    <div class="mb-6 flex items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">Historical gate register</h2>
            <p class="mt-1 text-sm text-muted">Older entries stay inside the role window.</p>
        </div>
        @if ($gate->canPrintHistory(auth()->user()))
            <a class="btn-ghost" href="{{ route('gates.history.print') }}">Print</a>
        @endif
    </div>
    @include('gates.partials.table', ['entries' => $entries])
</x-layouts.authenticated>
