<x-layouts.authenticated :title="$entry->vehicle_no">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ $entry->vehicle_no }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $entry->purpose }} · {{ $entry->status }} · Token {{ $entry->token }}</p>
        </div>
        @if ($gate->canPrintToken(auth()->user()))
            <a class="btn-ghost" href="{{ route('gates.token', $entry) }}">Print token</a>
        @endif
    </div>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($blocked)<p class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm">Blocked vehicle or driver.</p>@endif
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Entry</h3>
            <p class="mt-2 text-sm">Driver {{ $entry->driver_name ?? '—' }} · Entered {{ $entry->entered_at ?? $entry->created_at }}</p>
            @if ($gate->canSeeSeal(auth()->user()))
                <p class="mt-1 text-sm">Seal {{ $entry->seal_number ?? '—' }} · Condition {{ $entry->vehicle_condition ?? '—' }}</p>
            @endif
            @if ($gate->canEditOpen(auth()->user(), $entry) || ($entry->status === 'closed' && $gate->canEditClosed(auth()->user())))
                <form method="POST" action="{{ route('gates.update', $entry) }}" class="mt-4 grid gap-2">
                    @csrf @method('PUT')
                    <input name="vehicle_no" value="{{ $entry->vehicle_no }}" required class="field">
                    <input name="driver_name" value="{{ $entry->driver_name }}" class="field">
                    <select name="purpose" class="field"><option value="inbound">Inbound</option><option value="outbound" @selected($entry->purpose === 'outbound')>Outbound</option></select>
                    @if ($gate->canRecordSeal(auth()->user()))
                        <input name="seal_number" value="{{ $entry->seal_number }}" placeholder="Seal number" class="field">
                        <input name="vehicle_condition" value="{{ $entry->vehicle_condition }}" placeholder="Condition" class="field">
                    @endif
                    @if ($entry->status === 'closed' && auth()->user()->hasRole(\App\Enums\RoleName::Manager))
                        <input name="reason" required placeholder="Reason" class="field">
                    @endif
                    <button class="btn-primary" type="submit">Save entry</button>
                </form>
            @endif
            @if ($gate->canDelete(auth()->user()))
                <form method="POST" action="{{ route('gates.destroy', $entry) }}" class="mt-3 flex gap-2">@csrf @method('DELETE')<input name="reason" required placeholder="Reason" class="field"><button class="btn-ghost" type="submit">Remove</button></form>
            @endif
        </section>
        <section class="rounded-2xl border border-line bg-panel p-5">
            <h3 class="font-semibold">Release</h3>
            @if ($gate->canPhoto(auth()->user()))
                <form method="POST" action="{{ route('gates.photo', $entry) }}" enctype="multipart/form-data" class="mt-3 flex gap-2">@csrf<input name="photo" type="file" accept="image/*" required class="field"><button class="btn-ghost" type="submit">Store photo</button></form>
            @endif
            @if ($gate->canVerify(auth()->user()) && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.verify', $entry) }}" class="mt-3 flex gap-2">@csrf<input name="code" required placeholder="Gate pass or QR code" class="field"><button class="btn-ghost" type="submit">Verify pass</button></form>
            @endif
            @if ($gate->canOverride(auth()->user()) && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.override', $entry) }}" class="mt-3 flex gap-2">@csrf<input name="reason" required placeholder="Override reason" class="field"><button class="btn-ghost" type="submit">Approve override</button></form>
            @endif
            @if ($gate->canExit(auth()->user()) && $entry->status === 'open')
                <form method="POST" action="{{ route('gates.exit', $entry) }}" class="mt-3">@csrf<button class="btn-primary" type="submit">Mark exit</button></form>
            @endif
            @if ($entry->status === 'closed')<p class="mt-3 text-sm">Exited {{ $entry->exited_at }}</p>@endif
        </section>
    </div>
</x-layouts.authenticated>
