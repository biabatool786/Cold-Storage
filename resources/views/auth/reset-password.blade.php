<x-layouts.guest title="Reset password">
<h1 class="text-3xl font-semibold tracking-tight">Reset password</h1>
<p class="mt-2 text-sm text-muted">Choose a new password for this account.</p>

<form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autofocus class="field">
        @error('email')
            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="field-label">New password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" class="field">
        @error('password')
            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="field-label">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
    </div>

    <button type="submit" class="btn-primary w-full">Reset password</button>
</form>
</x-layouts.guest>
