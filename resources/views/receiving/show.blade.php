<x-layouts.authenticated title="GRN details">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $receipt->number ?? 'GRN #'.$receipt->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $receipt->status }}@if($receipt->outcome) · {{ $receipt->outcome }}@endif</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($desk->canPrint(auth()->user()))
                <a class="btn-ghost" href="{{ route('grns.print', $receipt) }}" target="_blank">Print GRN</a>
                @if ($receipt->lot)<a class="btn-ghost" href="{{ route('grns.labels', $receipt) }}" target="_blank">Lot labels</a>@endif
            @endif
            @if ($desk->canEditDraft(auth()->user(), $receipt) || ($receipt->status === 'approved' && $desk->canEditApproved(auth()->user())))
                <a class="btn-ghost" href="{{ route('grns.edit', $receipt) }}">Edit</a>
            @endif
        </div>
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <ul class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
            <h3 class="mb-3 font-semibold">Links</h3>
            <p>Vehicle: {{ $receipt->gateEntry?->vehicle_no ?? '—' }}</p>
            <p>Gate: #{{ $receipt->gate_entry_id }}</p>
            @unless ($limited)
                <p>Customer: {{ $receipt->customer?->name }}</p>
                <p>Product: {{ $receipt->commodity?->name }}</p>
                <p>Booking: {{ $receipt->booking_id ? '#'.$receipt->booking_id : '—' }}</p>
            @endunless
            <p>Weighment: {{ $receipt->weighment?->number ?? ($receipt->weighment_id ? '#'.$receipt->weighment_id : '—') }}</p>
            @if ($receipt->weighment)
                <p>Gross / Tare / Net: {{ $receipt->weighment->gross_weight ?? '—' }} / {{ $receipt->weighment->tare_weight ?? '—' }} / {{ $receipt->weighment->net_weight ?? '—' }}</p>
            @endif
        </div>
        <div class="rounded-2xl border border-line bg-panel p-5 text-sm">
            <h3 class="mb-3 font-semibold">Quantities &amp; inspection</h3>
            <p>Received: {{ $receipt->quantity }}</p>
            <p>Accepted: {{ $receipt->accepted_qty ?? '—' }}</p>
            <p>Shortage / Damage: {{ $receipt->shortage_qty ?? '—' }} / {{ $receipt->damage_qty ?? '—' }}</p>
            @unless ($limited)
                <p>Grade: {{ $receipt->grade ?? '—' }}</p>
                <p>Temperature: {{ $receipt->temperature ?? '—' }}</p>
                <p>Loaders: {{ $receipt->loader_count ?? '—' }}</p>
                <p>Condition: {{ $receipt->condition_notes ?? '—' }}</p>
                <p>Unloading: {{ $receipt->unloading_notes ?? '—' }}</p>
                <p>Notes: {{ $receipt->inspection_notes ?? '—' }}</p>
            @endunless
            <p>Lot: {{ $receipt->lot?->lot_number ?? '—' }} ({{ $receipt->lot?->status ?? 'n/a' }})</p>
        </div>
    </div>

    @unless ($limited)
        @if ($desk->canEditDraft(auth()->user(), $receipt))
            <form method="POST" action="{{ route('grns.inspect', $receipt) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
                @csrf
                <h3 class="md:col-span-2 font-semibold">Quality inspection / unloading</h3>
                <input name="grade" placeholder="Grade" value="{{ $receipt->grade }}" class="field">
                <input name="temperature" type="number" step="0.01" placeholder="Temperature" value="{{ $receipt->temperature }}" class="field">
                <input name="loader_count" type="number" min="0" placeholder="Loader count" value="{{ $receipt->loader_count }}" class="field">
                <input name="shortage_qty" type="number" min="0" step="0.001" placeholder="Shortage" value="{{ $receipt->shortage_qty }}" class="field">
                <input name="damage_qty" type="number" min="0" step="0.001" placeholder="Damage" value="{{ $receipt->damage_qty }}" class="field">
                <textarea name="condition_notes" placeholder="Condition" class="field md:col-span-2" rows="2">{{ $receipt->condition_notes }}</textarea>
                <textarea name="unloading_notes" placeholder="Unloading" class="field md:col-span-2" rows="2">{{ $receipt->unloading_notes }}</textarea>
                <textarea name="inspection_notes" placeholder="Inspection notes" class="field md:col-span-2" rows="2">{{ $receipt->inspection_notes }}</textarea>
                <div class="md:col-span-2"><button class="btn-primary" type="submit">Save inspection</button></div>
            </form>

            <form method="POST" action="{{ route('grns.outcome', $receipt) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
                @csrf
                <h3 class="md:col-span-2 font-semibold">Accept / partial / reject</h3>
                <select name="outcome" class="field" required>
                    <option value="accept">Accept</option>
                    <option value="partial">Partial accept</option>
                    <option value="reject">Reject</option>
                </select>
                <input name="accepted_qty" type="number" min="0" step="0.001" placeholder="Accepted qty (partial)" class="field">
                <textarea name="notes" placeholder="Notes" class="field md:col-span-2" rows="2"></textarea>
                @if ($desk->canConfirmOutcome(auth()->user()))
                    <input type="hidden" name="confirm" value="1">
                    <div class="md:col-span-2"><button class="btn-primary" type="submit">Confirm outcome</button></div>
                @elseif ($desk->canProposeOutcome(auth()->user()))
                    <div class="md:col-span-2"><button class="btn-primary" type="submit">Propose outcome</button></div>
                @endif
            </form>

            <form method="POST" action="{{ route('grns.photos', $receipt) }}" enctype="multipart/form-data" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
                @csrf
                <h3 class="md:col-span-2 font-semibold">Damage / shortage photos</h3>
                <input type="file" name="photo" accept="image/*" required class="field">
                <select name="kind" class="field"><option value="damage">Damage</option><option value="shortage">Shortage</option><option value="other">Other</option></select>
                <input name="caption" placeholder="Caption" class="field md:col-span-2">
                <div class="md:col-span-2"><button class="btn-ghost" type="submit">Upload photo</button></div>
            </form>
        @endif

        @if ($receipt->photos->isNotEmpty())
            <div class="mb-6 rounded-2xl border border-line bg-panel p-5">
                <h3 class="mb-3 font-semibold">Photos</h3>
                <div class="flex flex-wrap gap-3">
                    @foreach ($receipt->photos as $photo)
                        <figure class="text-xs">
                            <img src="{{ asset('storage/'.$photo->path) }}" alt="{{ $photo->caption }}" class="h-28 w-28 rounded-xl object-cover">
                            <figcaption class="mt-1 text-muted">{{ $photo->kind }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($desk->canApprove(auth()->user()) && in_array($receipt->status, ['pending', 'draft'], true) && $receipt->outcome !== 'reject')
            <form method="POST" action="{{ route('grns.approve', $receipt) }}" class="mb-6 grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
                @csrf
                <h3 class="md:col-span-2 font-semibold">Approve GRN</h3>
                <select name="chamber_id" class="field" required>
                    @foreach ($chambers as $chamber)
                        <option value="{{ $chamber->id }}">{{ $chamber->code }} — {{ $chamber->name }}</option>
                    @endforeach
                </select>
                <select name="suggested_slot_id" class="field">
                    <option value="">Suggest first available slot</option>
                    @foreach ($slots as $slot)
                        <option value="{{ $slot->id }}">{{ $slot->code }}</option>
                    @endforeach
                </select>
                <div class="md:col-span-2"><button class="btn-primary" type="submit">Approve &amp; create lot</button></div>
            </form>
        @endif

        @if ($desk->canSend(auth()->user()) && in_array($receipt->status, ['approved', 'rejected'], true))
            <form method="POST" action="{{ route('grns.send', $receipt) }}" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <select name="channel" class="field w-auto"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select>
                <button class="btn-ghost" type="submit">Send GRN to customer</button>
            </form>
        @endif

        @if ($desk->canCancel(auth()->user()) && $receipt->status !== 'cancelled')
            <form method="POST" action="{{ route('grns.cancel', $receipt) }}" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf
                <input name="reason" required placeholder="Cancel reason" class="field">
                <button class="btn-ghost" type="submit">Cancel GRN</button>
            </form>
        @endif

        @if ($desk->canDelete(auth()->user()))
            <form method="POST" action="{{ route('grns.destroy', $receipt) }}" class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-line bg-panel p-5">
                @csrf @method('DELETE')
                <input name="reason" required placeholder="Delete reason" class="field">
                <button class="btn-ghost" type="submit">Delete GRN</button>
            </form>
        @endif
    @endunless
</x-layouts.authenticated>
