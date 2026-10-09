<x-layouts.authenticated :title="$ticket->number ?? 'Weighment'">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $ticket->number ?? '#'.$ticket->id }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $ticket->vehicle_no }} · {{ $ticket->status }} · Net {{ $ticket->net_weight ?? '—' }}</p>
        </div>
        @if ($desk->canPrint(auth()->user()))
            <a class="btn-ghost" href="{{ route('weighments.print', $ticket) }}">Print slip</a>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Weights</h3>
            <p class="mt-2 text-sm">Gross {{ $ticket->gross_weight ?? '—' }} · Tare {{ $ticket->tare_weight ?? '—' }} · Net {{ $ticket->net_weight ?? '—' }}</p>
            @if ($ticket->is_manual)<p class="mt-1 text-sm">Manual · {{ $ticket->manual_reason }}</p>@endif
            @if ($ticket->status === 'pending_approval' && $desk->canApprove(auth()->user()))
                <form method="POST" action="{{ route('weighments.approve', $ticket) }}" class="mt-3">@csrf<button class="btn-primary" type="submit">Approve manual</button></form>
            @endif
            @if ($desk->canEditDirect(auth()->user()) || $desk->canProposeEdit(auth()->user()))
                <form method="POST" action="{{ route('weighments.update', $ticket) }}" class="mt-4 grid gap-2">
                    @csrf @method('PUT')
                    <input name="gross_weight" type="number" step="0.001" value="{{ $ticket->gross_weight }}" placeholder="Gross" class="field">
                    <input name="tare_weight" type="number" step="0.001" value="{{ $ticket->tare_weight }}" placeholder="Tare" class="field">
                    @unless($desk->canEditDirect(auth()->user()))
                        <input name="reason" required placeholder="Reason" class="field">
                    @endunless
                    <button class="btn-ghost" type="submit">{{ $desk->canEditDirect(auth()->user()) ? 'Save' : 'Submit edit' }}</button>
                </form>
            @endif
            @foreach ($ticket->editRequests as $edit)
                <p class="mt-2 text-sm">Edit {{ $edit->gross_weight }}/{{ $edit->tare_weight }} · {{ $edit->status }}
                    @if ($edit->status === 'pending' && $desk->canApprove(auth()->user()))
                        <form method="POST" action="{{ route('weighments.edits.decide', $edit) }}" class="inline">@csrf<input type="hidden" name="approve" value="1"><button class="btn-ghost px-2 py-1" type="submit">Approve</button></form>
                    @endif
                </p>
            @endforeach
            @if ($desk->canDelete(auth()->user()))
                <form method="POST" action="{{ route('weighments.destroy', $ticket) }}" class="mt-3 flex gap-2">@csrf @method('DELETE')<input name="reason" required placeholder="Reason" class="field"><button class="btn-ghost" type="submit">Remove</button></form>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Tolerance</h3>
            <p class="mt-2 text-sm">{{ $ticket->tolerance_status }}</p>
            @if ($ticket->tolerance_status === 'alert' && $desk->canAcknowledge(auth()->user()))
                <form method="POST" action="{{ route('weighments.acknowledge', $ticket) }}" class="mt-3 flex gap-2">@csrf<input name="comment" placeholder="Comment" class="field"><button class="btn-ghost" type="submit">Acknowledge</button></form>
            @endif
        </section>
    </div>
</x-layouts.authenticated>
