<x-layouts.authenticated title="Profile">
    <div class="max-w-xl rounded-2xl border border-line bg-panel p-5 sm:p-6">
        <h2 class="text-2xl font-semibold tracking-tight">Profile</h2>
        <p class="mt-1 text-sm text-muted">Update your name, email, or password. Your tenant is assigned by the system.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="field-label">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="field">
                @error('name')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="field-label">Phone</label>
                <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="field">
            </div>

            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="field">
                @error('email')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="current_password" class="field-label">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="field">
                <p class="mt-1 text-xs text-muted">Required only when you set a new password.</p>
                @error('current_password')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="field-label">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" class="field">
                @error('password')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="field-label">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="field">
            </div>

            <button type="submit" class="btn-primary">Save profile</button>
        </form>
        <form method="POST" action="{{ route('profile.two-factor.start') }}" class="mt-6">
            @csrf
            <button class="btn-ghost" type="submit">Set up authenticator</button>
        </form>
        @if (session('two_factor_secret'))
            <form method="POST" action="{{ route('profile.two-factor.confirm') }}" class="mt-4 space-y-2">
                @csrf
                <p class="text-sm">Enter this secret in an authenticator app: <span class="font-medium">{{ session('two_factor_secret') }}</span></p>
                <input name="code" required class="field" placeholder="6-digit code">
                <button class="btn-primary" type="submit">Confirm authenticator</button>
            </form>
        @endif
    </div>
</x-layouts.authenticated>
