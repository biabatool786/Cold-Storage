<x-layouts.authenticated title="Value-added services">
    <div class="mb-6"><h2 class="text-2xl font-semibold tracking-tight">Value-added services</h2><p class="mt-1 text-sm text-muted">Sorting, grading, repacking — Manager/SA approval required.</p></div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($desk->canCreateService(auth()->user()) && $lots->isNotEmpty())
        <form method="POST" action="{{ route('inventory.services.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select name="storage_lot_id" class="field" required>@foreach($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>@endforeach</select>
            <select name="service_type" class="field"><option value="sorting">Sorting</option><option value="grading">Grading</option><option value="repacking">Repacking</option></select>
            <input name="quantity" type="number" min="0" step="0.001" placeholder="Quantity" class="field">
            <input name="notes" placeholder="Notes" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Create order</button></div>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($services as $service)
            <div class="rounded-2xl border border-line bg-panel p-5 text-sm flex flex-wrap items-center justify-between gap-2">
                <div><p class="font-semibold">{{ $service->lot?->lot_number }} · {{ $service->service_type }} · {{ $service->status }}</p><p class="text-muted">{{ $service->notes }}</p></div>
                @if ($service->status === 'pending' && $desk->canApproveService(auth()->user()))
                    <form method="POST" action="{{ route('inventory.services.approve', $service) }}">@csrf<button class="btn-ghost" type="submit">Approve</button></form>
                @endif
            </div>
        @endforeach
    </div>
</x-layouts.authenticated>
