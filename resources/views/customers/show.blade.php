<x-layouts.authenticated :title="$customer->name">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold tracking-tight">{{ $customer->name }}</h2>
        @unless($master->seesNameOnly(auth()->user()))
            <p class="mt-1 text-sm text-muted">{{ $customer->status }} · {{ $customer->phone ?: 'No phone' }}</p>
        @endunless
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif

    @unless($master->seesNameOnly(auth()->user()))
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">Basic information</h3>
                <p class="mt-2 text-sm">{{ $customer->email ?: '—' }} · {{ $customer->address ?: '—' }} · {{ $customer->city ?: '—' }}</p>
                @if ($master->canEditBasic(auth()->user()))
                    <form method="POST" action="{{ route('customers.update', $customer) }}" class="mt-4 grid gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $customer->name }}" class="field" required>
                        <input name="phone" value="{{ $customer->phone }}" class="field" placeholder="Phone">
                        <input name="email" value="{{ $customer->email }}" class="field" placeholder="Email">
                        <input name="address" value="{{ $customer->address }}" class="field" placeholder="Address">
                        <input name="city" value="{{ $customer->city }}" class="field" placeholder="City">
                        <input name="reason" class="field" placeholder="Reason if this party is already approved">
                        <button class="btn-primary" type="submit">Save basic details</button>
                    </form>
                @endif
            </section>
            @if ($master->seesFinancials(auth()->user()))
                <section class="rounded-2xl border border-line bg-panel p-5">
                    <h3 class="font-semibold">Billing</h3>
                    <p class="mt-2 text-sm">{{ $customer->billing_name ?: '—' }} · {{ $customer->tax_no ?: '—' }}</p>
                    <p class="text-sm text-muted">{{ $customer->billing_address ?: '—' }}</p>
                    @if ($master->canEditBilling(auth()->user()))
                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="mt-4 grid gap-2">
                            @csrf @method('PUT')
                            <input name="billing_name" value="{{ $customer->billing_name }}" class="field" placeholder="Billing name">
                            <input name="tax_no" value="{{ $customer->tax_no }}" class="field" placeholder="Tax number">
                            <input name="billing_address" value="{{ $customer->billing_address }}" class="field" placeholder="Billing address">
                            <input name="reason" class="field" placeholder="Reason if this party is already approved">
                            <button class="btn-primary" type="submit">Save billing</button>
                        </form>
                    @endif
                </section>
                <section class="rounded-2xl border border-line bg-panel p-5">
                    <h3 class="font-semibold">Credit limit</h3>
                    <p class="mt-2 text-sm">{{ $customer->credit_limit }}</p>
                    @if ($master->canSetCredit(auth()->user()) || $master->canProposeCredit(auth()->user()))
                        <form method="POST" action="{{ route('customers.credit', $customer) }}" class="mt-4 flex gap-2">
                            @csrf
                            <input name="credit_limit" type="number" min="0" step="0.01" class="field" required>
                            <input name="reason" class="field" placeholder="Reason">
                            <button class="btn-ghost" type="submit">{{ $master->canSetCredit(auth()->user()) ? 'Set' : 'Propose' }}</button>
                        </form>
                    @endif
                    @foreach ($customer->creditChanges as $change)
                        <p class="mt-2 text-sm">{{ $change->amount }} · {{ $change->status }}
                            @if ($change->status === 'pending' && auth()->user()->hasRole(\App\Enums\RoleName::Manager))
                                <form method="POST" action="{{ route('customers.credit.approve', $change) }}" class="inline">@csrf<button class="btn-ghost px-2 py-1" type="submit">Approve</button></form>
                            @endif
                        </p>
                    @endforeach
                </section>
                <section class="rounded-2xl border border-line bg-panel p-5">
                    <h3 class="font-semibold">Rate card</h3>
                    @foreach ($customer->rateAgreements as $rate)<p class="mt-2 text-sm">{{ $rate->rate }} / {{ $rate->unit }}</p>@endforeach
                    @if (auth()->user()->hasPermission('customers.edit') && ! auth()->user()->hasRole(\App\Enums\RoleName::Accountant))
                        <form method="POST" action="{{ route('customers.rates.store', $customer) }}" class="mt-4 flex gap-2">
                            @csrf
                            <input name="rate" type="number" min="0" step="0.01" required class="field" placeholder="Rate">
                            <button class="btn-ghost" type="submit">Assign</button>
                        </form>
                    @endif
                </section>
            @endif
            <section class="rounded-2xl border border-line bg-panel p-5">
                <h3 class="font-semibold">KYC documents</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($customer->documents as $document)
                        <li><a href="{{ route('customers.documents.show', $document) }}">{{ $document->original_name }}</a></li>
                    @empty
                        <li class="text-muted">No documents.</li>
                    @endforelse
                </ul>
                @if (auth()->user()->hasPermission('customers.create'))
                    <form method="POST" action="{{ route('customers.documents.store', $customer) }}" enctype="multipart/form-data" class="mt-4 flex gap-2">
                        @csrf
                        <input type="file" name="document" required class="field">
                        <button class="btn-ghost" type="submit">Upload</button>
                    </form>
                @endif
            </section>
            @if ($master->seesFinancials(auth()->user()))
                <section class="rounded-2xl border border-line bg-panel p-5">
                    <h3 class="font-semibold">Hold / blacklist</h3>
                    <p class="mt-2 text-sm">{{ $customer->hold_reason ?: 'No hold reason.' }}</p>
                    @if ($master->canHold(auth()->user()))
                        <form method="POST" action="{{ route('customers.hold', $customer) }}" class="mt-4 flex gap-2">
                            @csrf
                            <select name="status" class="field w-auto"><option value="hold">Hold</option><option value="blacklisted">Blacklist</option></select>
                            <input name="reason" required class="field" placeholder="Reason">
                            <button class="btn-ghost" type="submit">Apply</button>
                        </form>
                    @endif
                    @if ($master->canRemoveHold(auth()->user()) && in_array($customer->status, ['hold', 'blacklisted'], true))
                        <form method="POST" action="{{ route('customers.release', $customer) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input name="reason" required class="field" placeholder="Reason">
                            <button class="btn-ghost" type="submit">Remove hold</button>
                        </form>
                    @endif
                    @if ($customer->status === 'pending' && $master->canApprove(auth()->user()))
                        <form method="POST" action="{{ route('customers.approve', $customer) }}" class="mt-3">@csrf<button class="btn-primary" type="submit">Approve customer</button></form>
                    @endif
                    @if (auth()->user()->hasPermission('customers.delete'))
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-3 flex gap-2">
                            @csrf @method('DELETE')
                            <input name="reason" required class="field" placeholder="Reason for removal">
                            <button class="btn-ghost" type="submit">Remove customer</button>
                        </form>
                    @endif
                </section>
            @endif
            @if ($master->canNote(auth()->user()))
                <section class="rounded-2xl border border-line bg-panel p-5">
                    <h3 class="font-semibold">Communication notes</h3>
                    @foreach ($customer->notes as $note)
                        <p class="mt-2 text-sm">{{ $note->channel }} · {{ $note->status }} · {{ $note->body }}</p>
                    @endforeach
                    <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mt-4 flex gap-2">
                        @csrf
                        <input name="body" required class="field" placeholder="Note">
                        <button class="btn-ghost" type="submit">Add note</button>
                    </form>
                    @if ($master->canMessage(auth()->user()))
                        <form method="POST" action="{{ route('customers.messages.store', $customer) }}" class="mt-3 flex gap-2">
                            @csrf
                            <select name="channel" class="field w-auto"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select>
                            <input name="body" required class="field" placeholder="Message">
                            <button class="btn-ghost" type="submit">Request send</button>
                        </form>
                    @endif
                </section>
            @endif
            <section class="rounded-2xl border border-line bg-panel p-5 lg:col-span-2">
                <h3 class="font-semibold">Activity</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse ($activity as $entry)
                        <li>{{ $entry->created_at }} · {{ $entry->action }}</li>
                    @empty
                        <li class="text-muted">No activity yet.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endunless
</x-layouts.authenticated>
