<x-layouts.authenticated title="New expense">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $supervisorOnly ? 'Petty-cash request' : 'New expense' }}</h2>
        <p class="mt-1 text-sm text-muted">@if($supervisorOnly) Supervisors may submit petty-cash requests only.@else Amounts above the manager limit require Super Admin approval.@endif</p>
    </div>
    <form method="POST" action="{{ route('expenses.store', $branches->first()) }}" class="grid max-w-3xl gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2" id="exp-form">
        @csrf
        <label class="text-sm md:col-span-2">Branch
            <select class="field mt-1" onchange="document.getElementById('exp-form').action='{{ url('/branches') }}/'+this.value+'/expenses'">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </label>
        @if ($supervisorOnly)
            <input type="hidden" name="kind" value="petty_cash_request">
        @else
            <label class="text-sm">Kind
                <select name="kind" class="field mt-1">
                    <option value="expense">Expense</option>
                    <option value="petty_cash_request">Petty-cash request</option>
                </select>
            </label>
        @endif
        <label class="text-sm">Category
            <input name="category" class="field mt-1" value="{{ $supervisorOnly ? 'petty_cash' : 'general' }}">
        </label>
        <label class="text-sm">Date
            <input name="expense_date" type="date" required value="{{ now()->toDateString() }}" class="field mt-1">
        </label>
        <label class="text-sm">Amount
            <input name="amount" type="number" step="0.01" min="0.01" required class="field mt-1">
        </label>
        <label class="text-sm md:col-span-2">Description
            <input name="description" required class="field mt-1">
        </label>
        <label class="text-sm">Payment method
            <select name="payment_method" class="field mt-1">
                <option value="">—</option>
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
                <option value="cheque">Cheque</option>
                <option value="wallet">Wallet</option>
            </select>
        </label>
        <label class="text-sm">Reference
            <input name="reference" class="field mt-1">
        </label>
        <label class="text-sm">Chamber
            <select name="chamber_id" class="field mt-1"><option value="">—</option>
                @foreach ($chambers as $chamber)<option value="{{ $chamber->id }}">{{ $chamber->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm">Customer
            <select name="customer_id" class="field mt-1"><option value="">—</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach
            </select>
        </label>
        <div class="md:col-span-2"><button class="btn-primary" type="submit">Submit</button></div>
    </form>
</x-layouts.authenticated>
