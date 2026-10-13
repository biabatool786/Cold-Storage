<x-layouts.authenticated title="Gateway Settings">
    <h2 class="text-2xl font-semibold tracking-tight mb-1">Communication gateways</h2>
    <p class="text-sm text-muted mb-6">Credentials are stored encrypted and never shown back in full.</p>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <div class="space-y-6">
        @foreach(['email','sms','whatsapp'] as $channel)
            @php $g = $gateways[$channel]; @endphp
            <form method="POST" action="{{ route('notifications.gateways.store') }}" class="rounded-2xl border border-line bg-panel p-5 grid gap-3 md:grid-cols-2">
                @csrf
                <input type="hidden" name="channel" value="{{ $channel }}">
                <h3 class="md:col-span-2 font-medium uppercase">{{ $channel }} — <span class="text-muted">{{ $g['status'] }}</span></h3>
                <label class="text-sm">Provider<input name="provider" class="field mt-1" value="{{ $g['provider'] ?? '' }}"></label>
                <label class="text-sm">From / sender<input name="from_address" class="field mt-1" value="{{ $g['from_address'] ?? '' }}"></label>
                <label class="text-sm">API key<input name="api_key" type="password" class="field mt-1" placeholder="{{ !empty($g['has_credentials']) ? '•••••••• (unchanged if blank)' : 'Optional' }}"></label>
                <label class="text-sm">Token<input name="token" type="password" class="field mt-1" placeholder="Optional"></label>
                <label class="text-sm flex items-center gap-2 mt-6"><input type="checkbox" name="is_enabled" value="1" @checked(!empty($g['is_enabled']))> Enabled</label>
                <div class="md:col-span-2 flex gap-2">
                    <button class="btn-primary" type="submit">Save</button>
                </div>
            </form>
        @endforeach
    </div>
</x-layouts.authenticated>
