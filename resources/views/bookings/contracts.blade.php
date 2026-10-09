<x-layouts.authenticated title="Seasonal contracts">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Seasonal contracts</h2>
        <p class="mt-1 text-sm text-muted">Seasonal rates stay on the existing rate agreement.</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($reservations->canCreateContract(auth()->user()) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('bookings.contracts.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            <select class="field md:col-span-2" onchange="this.form.action = this.value">
                @foreach ($branches as $branch)<option value="{{ route('bookings.contracts.store', $branch) }}">{{ $branch->name }}</option>@endforeach
            </select>
            <select name="customer_id" required class="field"><option value="">Customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select>
            <select name="commodity_id" class="field"><option value="">Product</option>@foreach ($commodities as $commodity)<option value="{{ $commodity->id }}">{{ $commodity->name }}</option>@endforeach</select>
            <input name="rate" type="number" min="0" step="0.01" required placeholder="Rate" class="field">
            <input name="unit" value="kg" class="field">
            <input name="starts_on" type="date" class="field">
            <input name="ends_on" type="date" class="field">
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Submit contract</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="px-4 py-3">Rate</th><th class="px-4 py-3">Season</th><th class="px-4 py-3">Decision</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($contracts as $contract)
                    <tr>
                        <td class="px-4 py-3">{{ $contract->rate }} / {{ $contract->unit }}</td>
                        <td class="px-4 py-3">{{ $contract->starts_on?->toDateString() ?? '—' }} – {{ $contract->ends_on?->toDateString() ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $contract->season_status }}</td>
                        <td class="px-4 py-3">
                            @if ($contract->season_status === 'pending' && $reservations->canApprove(auth()->user()))
                                <form method="POST" action="{{ route('bookings.contracts.decide', $contract) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost" type="submit">Approve</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="4">No seasonal contracts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
