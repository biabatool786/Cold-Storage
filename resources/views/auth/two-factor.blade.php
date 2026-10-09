<x-layouts.guest title="Authenticator">
    <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4">
        @csrf
        <h1 class="text-2xl font-semibold">Authenticator code</h1>
        <p class="text-sm text-muted">Enter the 6-digit code from your authenticator app.</p>
        <input name="code" inputmode="numeric" required class="field" autofocus>
        @error('code')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
        <button class="btn-primary" type="submit">Continue</button>
    </form>
</x-layouts.guest>
