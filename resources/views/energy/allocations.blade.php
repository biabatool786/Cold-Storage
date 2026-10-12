<x-layouts.authenticated title="Energy billing allocation">
    @php $user = auth()->user(); @endphp
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">Energy billing allocation</h2>
        <p class="mt-1 text-sm text-muted">Configurable surcharge proposals. Posted invoices are never edited — debit notes are used after approval. Allocation formula is not defined in the matrix.</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @if ($desk->canProposeAllocation($user) && $branches->isNotEmpty())
        <form method="POST" action="{{ route('energy.allocations.store', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <label class="text-sm">Branch
                <select class="field mt-1" onchange="this.form.action='{{ url('/branches') }}/'+this.value+'/energy/allocations'">
                    @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm">Period start<input name="period_start" type="date" required class="field mt-1"></label>
            <label class="text-sm">Period end<input name="period_end" type="date" required class="field mt-1"></label>
            <label class="text-sm">Energy cost<input name="energy_cost" type="number" step="0.01" required class="field mt-1"></label>
            <label class="text-sm">kWh total<input name="kwh_total" type="number" step="0.001" class="field mt-1"></label>
            <label class="text-sm">Posted invoice (optional)
                <select name="storage_invoice_id" class="field mt-1">
                    <option value="">— proposal only —</option>
                    @foreach ($invoices as $inv)<option value="{{ $inv->id }}">{{ $inv->number ?? ('INV-'.$inv->id) }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm md:col-span-3">Notes<textarea name="calculation_notes" class="field mt-1" rows="2" placeholder="Describe basis; tariff formula not defined in matrix"></textarea></label>
            <div class="md:col-span-3"><button class="btn-primary" type="submit">Propose allocation</button></div>
        </form>
    @endif
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Period</th><th class="px-4 py-3">Cost</th><th class="px-4 py-3">Basis</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($allocations as $a)
                    <tr>
                        <td class="px-4 py-3">{{ optional($a->period_start)->toDateString() }} – {{ optional($a->period_end)->toDateString() }}</td>
                        <td class="px-4 py-3">{{ number_format((float)$a->energy_cost, 2) }}</td>
                        <td class="px-4 py-3">{{ $a->allocation_basis }}</td>
                        <td class="px-4 py-3">{{ $a->status }}</td>
                        <td class="px-4 py-3">
                            @if ($desk->canApproveAllocation($user) && $a->status === 'pending')
                                <form method="POST" action="{{ route('energy.allocations.decide', $a) }}" class="inline">@csrf
                                    <input type="hidden" name="approve" value="1">
                                    <button class="btn-ghost" type="submit">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('energy.allocations.decide', $a) }}" class="inline">@csrf
                                    <input type="hidden" name="approve" value="0">
                                    <button class="btn-ghost" type="submit">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="5">No allocations.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
