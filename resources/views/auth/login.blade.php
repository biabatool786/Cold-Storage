<x-layouts.guest title="Sign in">
<h1 class="hidden text-3xl font-semibold tracking-tight lg:block">Welcome back</h1>
<p class="mt-2 text-sm text-muted">Sign in to the cold store you belong to.</p>

<form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
    @csrf
    @if (session('status'))
        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
        @error('email')
            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="field-label">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
        @error('password')
            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </div>

    <label class="flex items-center gap-2 text-sm text-muted">
        <input type="checkbox" name="remember" value="1" class="rounded border-line bg-panel text-accent">
        Remember me
    </label>

    <button type="submit" class="btn-primary w-full">Sign in</button>
</form>

<p class="mt-4 text-sm text-muted">
    <a href="{{ route('password.request') }}" class="font-medium text-accent">Forgot your password?</a>
</p>
</x-layouts.guest>
