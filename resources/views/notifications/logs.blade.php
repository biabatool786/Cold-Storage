<x-layouts.authenticated title="Message Log">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Message log</h2>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="channel" class="field"><option value="">Channel</option>@foreach(['email','sms','whatsapp'] as $c)<option value="{{ $c }}" @selected(request('channel')===$c)>{{ $c }}</option>@endforeach</select>
        <select name="status" class="field"><option value="">Status</option>@foreach(['queued','sent','delivered','failed'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
        <input type="date" name="from" class="field" value="{{ request('from') }}">
        <input type="date" name="to" class="field" value="{{ request('to') }}">
        <button class="btn-secondary" type="submit">Filter</button>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-line bg-panel">
        <table class="min-w-full text-sm">
            <thead class="text-xs uppercase text-muted"><tr>
                <th class="px-4 py-3">Template</th><th class="px-4 py-3">Channel</th><th class="px-4 py-3">Recipient</th>
                <th class="px-4 py-3">Status</th><th class="px-4 py-3">When</th><th class="px-4 py-3">Ref</th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse($logs as $l)
                    <tr>
                        <td class="px-4 py-3">{{ $l->template_name ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $l->channel }}</td>
                        <td class="px-4 py-3">{{ $l->recipient_reference }}</td>
                        <td class="px-4 py-3">{{ $l->status }}@if($l->failure_reason)<div class="text-xs text-red-700">{{ $l->failure_reason }}</div>@endif</td>
                        <td class="px-4 py-3">{{ $l->sent_at ?: $l->created_at }}</td>
                        <td class="px-4 py-3">{{ $l->provider_reference ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-muted" colspan="6">No messages.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.authenticated>
