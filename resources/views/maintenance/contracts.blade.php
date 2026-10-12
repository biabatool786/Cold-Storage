<x-layouts.authenticated title="AMC / Warranty">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">AMC &amp; warranty</h2>
            <p class="mt-1 text-sm text-muted">Active, expiring, and expired coverage.</p>
        </div>
        <a class="btn-ghost" href="{{ route('assets.index') }}">Assets</a>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canManageContracts($user))
        <form method="POST" action="{{ route('maintenance.contracts.store') }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm md:col-span-2">Asset
                <select name="facility_asset_id" required class="field mt-1">
                    @foreach ($assets as $a)<option value="{{ $a->id }}">{{ $a->asset_code }} — {{ $a->displayName() }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Type
                <select name="kind" class="field mt-1"><option value="warranty">Warranty</option><option value="amc">AMC</option></select>
            </label>
            <label class="text-sm">Provider<input name="provider_name" class="field mt-1"></label>
            <label class="text-sm">Reference<input name="reference_no" class="field mt-1"></label>
            <label class="text-sm">Vendor
                <select name="vendor_id" class="field mt-1">
                    <option value="">—</option>
                    @foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Start<input name="starts_on" type="date" required class="field mt-1"></label>
            <label class="text-sm">Expiry<input name="ends_on" type="date" required class="field mt-1"></label>
            <label class="text-sm">Cost<input name="cost" type="number" step="0.01" class="field mt-1"></label>
            <label class="text-sm md:col-span-3">Coverage<textarea name="coverage_notes" class="field mt-1" rows="2"></textarea></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Save contract</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Asset</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Expiry</th><th class="px-4 py-3">Status</th>
                @if ($showCost)<th class="px-4 py-3">Cost</th>@endif
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($contracts as $c)
                    <tr>
                        <td class="px-4 py-3">{{ $c->asset?->asset_code }}</td>
                        <td class="px-4 py-3">{{ $c->kind }}</td>
                        <td class="px-4 py-3">{{ $c->provider_name ?: ($c->vendor?->name ?: '—') }}</td>
                        <td class="px-4 py-3">{{ optional($c->ends_on)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $c->expiryLabel() }}</td>
                        @if ($showCost)<td class="px-4 py-3">{{ $c->cost !== null ? number_format((float)$c->cost, 2) : '—' }}</td>@endif
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No contracts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
