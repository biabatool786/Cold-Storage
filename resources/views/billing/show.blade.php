<x-layouts.authenticated title="Invoice">
    @php $user = auth()->user(); @endphp
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $invoice->number ?? 'Invoice #'.$invoice->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $invoice->kind }} · {{ $invoice->balanceDisplay() }} · {{ $invoice->customer?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($desk->canPrint($user) || $desk->canView($user))
                <a class="btn-ghost" href="{{ route('invoices.print', $invoice) }}" target="_blank">Print / PDF</a>
            @endif
            @if ($desk->canSend($user))
                <form method="POST" action="{{ route('invoices.send', $invoice) }}">@csrf<button class="btn-ghost" type="submit">Send</button></form>
            @endif
        </div>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-3 text-sm">
        <div class="rounded-2xl border border-line bg-panel p-5">
            <p>Subtotal: {{ number_format((float) $invoice->subtotal, 2) }}</p>
            <p>Tax: {{ number_format((float) $invoice->tax_amount, 2) }}</p>
            <p>Discount: {{ number_format((float) $invoice->discount_amount, 2) }} ({{ $invoice->discount_percent }}%)</p>
            <p class="font-semibold">Total: {{ number_format((float) $invoice->amount, 2) }}</p>
            <p>Credited: {{ number_format((float) $invoice->credited_amount, 2) }} · Debited: {{ number_format((float) $invoice->debited_amount, 2) }}</p>
            <p>Net: {{ number_format($invoice->netAmount(), 2) }}</p>
        </div>
        <div class="rounded-2xl border border-line bg-panel p-5 md:col-span-2">
            <h3 class="mb-2 font-semibold">Lines</h3>
            @foreach ($invoice->lines as $line)
                <p class="mb-1">{{ $line->description }} · {{ $line->billable_days }}d · {{ number_format((float) $line->line_total, 2) }}</p>
            @endforeach
        </div>
    </div>

    @if ($desk->canEditDraft($user) && $invoice->isDraft())
        <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
            @csrf @method('PUT')
            <input name="due_date" type="date" value="{{ optional($invoice->due_date)->format('Y-m-d') }}" class="field">
            <input name="notes" value="{{ $invoice->notes }}" placeholder="Notes" class="field">
            <div class="md:col-span-2"><button class="btn-ghost" type="submit">Save draft</button></div>
        </form>
        @if ($desk->canApplyDiscount($user))
            <form method="POST" action="{{ route('invoices.discount', $invoice) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <input name="percent" type="number" step="0.01" min="0" max="100" required placeholder="Discount %" class="field w-auto">
                <input name="reason" required placeholder="Reason" class="field">
                <button class="btn-ghost" type="submit">Apply discount</button>
            </form>
        @endif
        @if ($desk->canAddManualCharge($user))
            <form method="POST" action="{{ route('invoices.manual-charge', $invoice) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-3">
                @csrf
                <input name="description" required placeholder="Manual charge" class="field">
                <input name="amount" type="number" step="0.01" required class="field">
                <input name="tax_percent" type="number" step="0.01" placeholder="Tax %" class="field">
                <div class="md:col-span-3"><button class="btn-ghost" type="submit">Add manual charge</button></div>
            </form>
        @endif
        @if ($desk->canPost($user))
            <form method="POST" action="{{ route('invoices.post', $invoice) }}" class="mb-4">@csrf<button class="btn-primary" type="submit">Post invoice</button></form>
        @endif
    @endif

    @if ($invoice->discount_status === 'pending' && $desk->canApproveDiscount($user))
        <div class="mb-4 flex gap-2">
            <form method="POST" action="{{ route('invoices.discount.decide', $invoice) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve discount</button></form>
            <form method="POST" action="{{ route('invoices.discount.decide', $invoice) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
        </div>
    @endif

    @if ($invoice->isPosted())
        @if ($user->hasPermission('payments.create'))
            <form method="POST" action="{{ route('invoices.pay', $invoice) }}" class="mb-4 flex gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <input name="amount" type="number" step="0.01" required placeholder="Payment amount" class="field">
                <button class="btn-ghost" type="submit">Draft payment</button>
            </form>
        @endif
        @if ($desk->canCreateAdjustment($user))
            <form method="POST" action="{{ route('invoices.adjustment', $invoice) }}" class="mb-4 grid gap-2 rounded-2xl border border-line bg-panel p-5 md:grid-cols-4">
                @csrf
                <select name="kind" class="field"><option value="credit">Credit note</option><option value="debit">Debit note</option></select>
                <input name="amount" type="number" step="0.01" required class="field">
                <input name="reason" required placeholder="Reason" class="field md:col-span-2">
                <div class="md:col-span-4"><button class="btn-ghost" type="submit">Create note</button></div>
            </form>
        @endif
        @if ($desk->canRequestWaiver($user) || $desk->canApproveWaiver($user))
            <form method="POST" action="{{ route('invoices.waiver', $invoice) }}" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <input name="amount" type="number" step="0.01" required placeholder="Waiver amount" class="field w-auto">
                <input name="reason" required placeholder="Late fee / demurrage waiver reason" class="field">
                <button class="btn-ghost" type="submit">Request waiver</button>
            </form>
        @endif
        @if ($desk->canRequestCancel($user) || $desk->canCancel($user))
            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="mb-4 flex gap-2">
                @csrf
                <input name="reason" required placeholder="Cancel reason" class="field">
                <button class="btn-ghost" type="submit">{{ $desk->canCancel($user) && ! $user->hasRole(\App\Enums\RoleName::Accountant) ? 'Cancel invoice' : 'Request cancellation' }}</button>
            </form>
        @endif
    @endif

    @if ($invoice->cancel_status === 'pending' && $desk->canCancel($user))
        <div class="mb-4 flex gap-2">
            <form method="POST" action="{{ route('invoices.cancel.decide', $invoice) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve cancel</button></form>
            <form method="POST" action="{{ route('invoices.cancel.decide', $invoice) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
        </div>
    @endif

    @foreach ($invoice->adjustments as $adj)
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-panel px-4 py-3 text-sm">
            <span>{{ strtoupper($adj->kind) }} {{ $adj->number }} · {{ number_format((float) $adj->amount, 2) }} · {{ $adj->status }} — {{ $adj->reason }}</span>
            @if ($adj->status === 'pending' && $desk->canApproveAdjustment($user))
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('billing.adjustments.decide', $adj) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                    <form method="POST" action="{{ route('billing.adjustments.decide', $adj) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                </div>
            @endif
        </div>
    @endforeach

    @foreach ($waivers as $waiver)
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-panel px-4 py-3 text-sm">
            <span>Waiver {{ number_format((float) $waiver->amount, 2) }} · {{ $waiver->status }} — {{ $waiver->reason }}</span>
            @if ($waiver->status === 'pending' && $desk->canApproveWaiver($user))
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('billing.waivers.decide', $waiver) }}">@csrf<input type="hidden" name="approve" value="1"><button class="btn-primary" type="submit">Approve</button></form>
                    <form method="POST" action="{{ route('billing.waivers.decide', $waiver) }}">@csrf<input type="hidden" name="approve" value="0"><button class="btn-ghost" type="submit">Reject</button></form>
                </div>
            @endif
        </div>
    @endforeach
</x-layouts.authenticated>
