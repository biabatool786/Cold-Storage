<x-layouts.guest title="Forgot password">
<h1 class="text-3xl font-semibold tracking-tight">Forgot password</h1>
<p class="mt-2 text-sm text-muted">Enter the email for your account. If it can sign in, we will send a reset link.</p>

<form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-4">
    @csrf
    @if (session('status'))
        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="field">
        @error('email')
            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="btn-primary w-full">Email reset link</button>
</form>

<p class="mt-4 text-sm text-muted">
    <a href="{{ route('login') }}" class="font-medium text-accent">Back to sign in</a>
</p>
</x-layouts.guest>
