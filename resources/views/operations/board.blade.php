<x-layouts.authenticated :title="$title">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ $title }}</h2>
        <p class="mt-1 text-sm text-muted">Records follow the signed-in cold store and assigned branches.</p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if ($canCreate && ($key === 'commodities' || $key === 'monitoring' || $branches->isNotEmpty()))
        <form method="POST" action="{{ $key === 'commodities' ? route('commodities.store') : ($key === 'monitoring' ? route('monitoring.store') : route($storeName, $branches->first())) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf
            @if ($branches->isNotEmpty() && ! in_array($key, ['commodities', 'monitoring'], true))
                <div class="md:col-span-2">
                    <label class="field-label" for="branch_target">Branch</label>
                    <select id="branch_target" class="field" onchange="this.form.action = this.value">
                        @foreach ($branches as $branch)
                            <option value="{{ route($storeName, $branch) }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($key === 'customers')
                <input name="name" placeholder="Customer name" required class="field">
                <input name="phone" placeholder="Phone" class="field">
                @unless(app(\App\Services\Authorization\FieldAccess::class)->denies(auth()->user(), 'credit_limit'))
                    <input name="credit_limit" type="number" min="0" step="0.01" placeholder="Credit limit" class="field">
                @endunless
            @elseif ($key === 'commodities')
                <input name="name" placeholder="Product" required class="field">
                <input name="variety" placeholder="Variety" class="field">
                <input name="grade" placeholder="Grade" class="field">
            @elseif ($key === 'chambers')
                <input name="name" placeholder="Chamber" required class="field">
                <input name="code" placeholder="Code" required class="field">
            @elseif ($key === 'bookings')
                <input name="customer_id" type="number" placeholder="Customer id" required class="field">
                <input name="commodity_id" type="number" placeholder="Product id" required class="field">
                <input name="quantity" type="number" step="0.001" placeholder="Quantity" required class="field">
            @elseif ($key === 'gates')
                <input name="vehicle_no" placeholder="Vehicle" required class="field">
                <input name="driver_name" placeholder="Driver" class="field">
                <select name="purpose" class="field"><option value="inbound">Inbound</option><option value="outbound">Outbound</option></select>
            @elseif ($key === 'monitoring')
                <select name="chamber_id" class="field" required>
                    @foreach ($chambers as $chamber)
                        <option value="{{ $chamber->id }}">{{ $chamber->name }}</option>
                    @endforeach
                </select>
                <input name="temperature" type="number" step="0.01" placeholder="Temperature" required class="field">
            @elseif ($key === 'invoices')
                <input name="customer_id" type="number" placeholder="Customer id" required class="field">
                <input name="delivery_order_id" type="number" placeholder="Delivery order id" class="field">
                <input name="amount" type="number" step="0.01" placeholder="Amount" required class="field">
            @elseif ($key === 'expenses')
                <input name="description" placeholder="Description" required class="field">
                <input name="amount" type="number" step="0.01" placeholder="Amount" required class="field">
            @elseif (in_array($key, ['maintenance', 'energy', 'labor', 'quality', 'notifications'], true))
                <input name="title" placeholder="Title" required class="field">
                <input name="detail" placeholder="Detail" class="field">
            @endif
            <div class="md:col-span-2"><button class="btn-primary" type="submit">Save</button></div>
        </form>
    @endif

    @if ($key === 'customers' && auth()->user()->hasPermission('customers.create') && $branches->isNotEmpty())
        <form method="POST" action="{{ route('customers.rate', $branches->first()) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
            @csrf
            <div class="md:col-span-3">
                <label class="field-label" for="rate_branch">Rate agreement branch</label>
                <select id="rate_branch" class="field" onchange="this.form.action = this.value">
                    @foreach ($branches as $branch)
                        <option value="{{ route('customers.rate', $branch) }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <input name="customer_id" type="number" required placeholder="Customer id" class="field">
            <input name="rate" type="number" step="0.01" min="0" required placeholder="Rate" class="field">
            <button class="btn-primary" type="submit">Save rate</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase tracking-wide text-muted">
                <tr>
                    @foreach ($columns as $column)
                        <th class="px-4 py-3 font-medium">{{ $column }}</th>
                    @endforeach
                    <th class="px-4 py-3 font-medium">Next step</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($records as $index => $record)
                    @php($visible = app(\App\Services\Authorization\FieldAccess::class)->filter(auth()->user(), $record->only(array_keys($columns))))
                    <tr>
                        @foreach (array_keys($columns) as $column)
                            <td class="px-4 py-3">
                                @if ($key === 'gates' && $column === 'vehicle_no')
                                    <a class="font-medium" href="{{ route('gates.show', $record) }}">{{ $visible[$column] ?? '—' }}</a>
                                @else
                                    {{ $visible[$column] ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-3">
                            @if ($key === 'bookings' && $record->status === 'pending' && auth()->user()->hasPermission('bookings.approve'))
                                <form method="POST" action="{{ route('bookings.approve', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Approve</button></form>
                            @elseif ($key === 'grns' && $record->status === 'pending' && auth()->user()->hasPermission('grn.approve'))
                                <form method="POST" action="{{ route('grns.approve', $record) }}" class="flex gap-2">
                                    @csrf
                                    <select name="chamber_id" class="field w-auto py-2" required>
                                        @foreach ($chambers->where('branch_id', $record->branch_id) as $chamber)
                                            <option value="{{ $chamber->id }}">{{ $chamber->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn-ghost px-3 py-2" type="submit">Put away</button>
                                </form>
                            @elseif ($key === 'lots' && $record->status === 'stored' && auth()->user()->hasPermission('dispatch.create'))
                                <form method="POST" action="{{ route('lots.deliver', $record) }}" class="flex gap-2">
                                    @csrf
                                    <input name="quantity" type="number" step="0.001" value="{{ $record->quantity }}" class="field w-28 py-2" required>
                                    <button class="btn-ghost px-3 py-2" type="submit">Delivery order</button>
                                </form>
                            @elseif ($key === 'deliveries' && $record->status === 'pending' && auth()->user()->hasPermission('dispatch.approve'))
                                <form method="POST" action="{{ route('deliveries.approve', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Approve</button></form>
                            @elseif ($key === 'deliveries' && $record->status === 'approved' && auth()->user()->hasPermission('dispatch.execute'))
                                <form method="POST" action="{{ route('deliveries.pass', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Pick and load</button></form>
                            @elseif ($key === 'deliveries' && $record->status === 'loaded' && auth()->user()->hasPermission('gate.execute'))
                                @php($pass = $record->gatePasses->firstWhere('status', 'issued'))
                                @if ($pass)
                                    <form method="POST" action="{{ route('passes.release', $pass) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Release</button></form>
                                @endif
                            @elseif ($key === 'invoices' && $record->status === 'draft' && auth()->user()->hasPermission('billing.execute'))
                                <form method="POST" action="{{ route('invoices.post', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Post</button></form>
                            @elseif ($key === 'invoices' && $record->status === 'posted' && auth()->user()->hasPermission('payments.create'))
                                <form method="POST" action="{{ route('invoices.pay', $record) }}" class="flex gap-2">
                                    @csrf
                                    <input name="amount" type="number" step="0.01" value="{{ $record->amount }}" class="field w-28 py-2" required>
                                    <button class="btn-ghost px-3 py-2" type="submit">Take payment</button>
                                </form>
                            @elseif ($key === 'payments' && $record->status === 'draft' && auth()->user()->hasPermission('payments.execute'))
                                <form method="POST" action="{{ route('payments.post', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Post</button></form>
                            @elseif ($key === 'expenses' && $record->status === 'pending' && auth()->user()->hasPermission('expenses.approve'))
                                <form method="POST" action="{{ route('expenses.approve', $record) }}">@csrf<button class="btn-ghost px-3 py-2" type="submit">Approve</button></form>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-muted">No records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
