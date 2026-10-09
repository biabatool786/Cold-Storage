<x-layouts.authenticated title="Settings">
    <form method="POST" action="{{ route('settings.update') }}" class="max-w-xl space-y-4 rounded-2xl border border-line bg-panel p-5">
        @csrf
        @method('PUT')
        <h2 class="text-2xl font-semibold tracking-tight">System settings</h2>
        <div>
            <label class="field-label" for="platform_name">Platform name</label>
            <input id="platform_name" name="platform_name" value="{{ old('platform_name', $platformName) }}" class="field" required>
        </div>
        <div>
            <label class="field-label" for="support_email">Support email</label>
            <input id="support_email" name="support_email" type="email" value="{{ old('support_email', $supportEmail) }}" class="field">
        </div>
        <button class="btn-primary" type="submit">Save settings</button>
    </form>
    <form method="POST" action="{{ route('settings.security') }}" class="mt-6 max-w-xl space-y-4 rounded-2xl border border-line bg-panel p-5">
        @csrf @method('PUT')
        <h2 class="text-2xl font-semibold tracking-tight">Security</h2>
        <label class="field-label" for="password_min_length">Password minimum length</label>
        <input id="password_min_length" name="password_min_length" type="number" min="8" value="{{ old('password_min_length', $policy->minimumLength()) }}" class="field">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="password_require_mixed" value="1"> Require upper and lower case</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="password_require_number" value="1"> Require a number</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="password_require_symbol" value="1"> Require a symbol</label>
        <label class="field-label" for="login_lock_after">Lock after failed sign-ins</label>
        <input id="login_lock_after" name="login_lock_after" type="number" min="1" value="{{ old('login_lock_after', 5) }}" class="field">
        <label class="field-label" for="session_timeout_minutes">Session timeout (minutes)</label>
        <input id="session_timeout_minutes" name="session_timeout_minutes" type="number" min="5" value="{{ old('session_timeout_minutes', $policy->sessionMinutes()) }}" class="field">
        <label class="field-label" for="two_factor_mode">Two-factor authentication</label>
        <select id="two_factor_mode" name="two_factor_mode" class="field">
            @foreach (['off' => 'Off', 'optional' => 'Optional', 'required' => 'Required'] as $value => $label)
                <option value="{{ $value }}" @selected($policy->twoFactorMode() === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ip_restriction_enabled" value="1" @checked($policy->ipRestrictionEnabled())> Restrict sign-in by IP</label>
        <label class="field-label" for="ip_allowlist">Allowed IPs or CIDR ranges</label>
        <textarea id="ip_allowlist" name="ip_allowlist" class="field" rows="4">{{ old('ip_allowlist', implode("\n", $policy->allowedIps())) }}</textarea>
        @error('ip_allowlist')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
        <button class="btn-primary" type="submit">Save security</button>
    </form>
</x-layouts.authenticated>
