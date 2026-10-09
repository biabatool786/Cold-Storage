<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\Setting;
use App\Services\Audit\AuditLogger;
use App\Services\Security\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() && $user->hasPermission(PermissionName::PlatformSettings)) {
            return view('settings.platform', [
                'supportEmail' => Setting::query()->where('key', 'support_email')->value('value'),
                'platformName' => Setting::query()->where('key', 'platform_name')->value('value') ?? config('app.name'),
                'policy' => app(PasswordPolicy::class),
            ]);
        }

        abort_unless($user?->hasPermission(PermissionName::TenantSettings) && $user->tenant, 403);

        return view('settings.tenant', ['tenant' => $user->tenant]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() && $user->hasPermission(PermissionName::PlatformSettings)) {
            $data = $request->validate([
                'platform_name' => ['required', 'string', 'max:255'],
                'support_email' => ['nullable', 'email', 'max:255'],
            ]);

            foreach ($data as $key => $value) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }

            $audit->record('platform.settings_updated', null, $data);

            return back()->with('status', 'Platform settings saved.');
        }

        abort(403);
    }

    public function updateTenant(\Illuminate\Http\Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        abort_unless($user?->hasPermission(PermissionName::TenantSettings) && $tenant, 403);
        abort_unless($user->can('update', $tenant), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant->update($data);
        $audit->record('tenant.settings_updated', $tenant, $data, $tenant->id);

        return back()->with('status', 'Cold storage settings saved.');
    }

    public function updateSecurity(Request $request, AuditLogger $audit, PasswordPolicy $policy): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin() && $request->user()->hasPermission(PermissionName::PlatformSettings), 403);
        $data = $request->validate([
            'password_min_length' => ['required', 'integer', 'min:8', 'max:128'],
            'password_require_mixed' => ['nullable', 'boolean'],
            'password_require_number' => ['nullable', 'boolean'],
            'password_require_symbol' => ['nullable', 'boolean'],
            'login_lock_after' => ['required', 'integer', 'min:1', 'max:20'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'two_factor_mode' => ['required', 'in:off,optional,required'],
            'ip_restriction_enabled' => ['nullable', 'boolean'],
            'ip_allowlist' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['password_require_mixed'] = $request->boolean('password_require_mixed') ? '1' : '0';
        $data['password_require_number'] = $request->boolean('password_require_number') ? '1' : '0';
        $data['password_require_symbol'] = $request->boolean('password_require_symbol') ? '1' : '0';
        $data['ip_restriction_enabled'] = $request->boolean('ip_restriction_enabled') ? '1' : '0';

        if ($data['ip_restriction_enabled'] === '1') {
            $rules = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($data['ip_allowlist'] ?? '')) ?: [])));
            if (! $policy->listAllows($request->ip(), $rules)) {
                return back()->withErrors(['ip_allowlist' => 'Include the network you are using before turning restriction on.'])->withInput();
            }
        }

        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $audit->record('platform.security_updated', null, $data);

        return back()->with('status', 'Security settings saved.');
    }
}
