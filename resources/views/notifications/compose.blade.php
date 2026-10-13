<x-layouts.authenticated title="Customer Message">
    <h2 class="text-2xl font-semibold tracking-tight mb-6">Send customer message</h2>
    @if (session('status'))<p class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('notifications.compose.send') }}" class="grid gap-3 rounded-2xl border border-line bg-panel p-5 md:grid-cols-2">
        @csrf
        <label class="text-sm">Customer<select name="customer_id" required class="field mt-1">@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email ?: $c->phone ?: 'no contact' }})</option>@endforeach</select></label>
        <label class="text-sm">Channel<select name="channel" class="field mt-1"><option>email</option><option>sms</option><option>whatsapp</option></select></label>
        <label class="text-sm md:col-span-2">Template<select name="message_template_id" class="field mt-1"><option value="">Custom body</option>@foreach($templates as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->channel }})</option>@endforeach</select></label>
        <label class="text-sm md:col-span-2">Subject<input name="subject" class="field mt-1"></label>
        <label class="text-sm md:col-span-2">Body<textarea name="body" class="field mt-1" rows="4"></textarea></label>
        <button class="btn-primary" type="submit">Send</button>
    </form>
</x-layouts.authenticated>
