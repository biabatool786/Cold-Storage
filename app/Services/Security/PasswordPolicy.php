<?php

namespace App\Services\Security;

use App\Models\Setting;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public function rule(): Password
    {
        $rule = Password::min($this->minimumLength());

        if ($this->flag('password_require_mixed')) {
            $rule = $rule->mixedCase();
        }
        if ($this->flag('password_require_number')) {
            $rule = $rule->numbers();
        }
        if ($this->flag('password_require_symbol')) {
            $rule = $rule->symbols();
        }

        return $rule;
    }

    public function minimumLength(): int
    {
        return max(8, (int) (Setting::query()->where('key', 'password_min_length')->value('value') ?: 8));
    }

    public function lockAfter(): int
    {
        return max(1, (int) (Setting::query()->where('key', 'login_lock_after')->value('value') ?: 5));
    }

    public function sessionMinutes(): int
    {
        return max(5, (int) (Setting::query()->where('key', 'session_timeout_minutes')->value('value') ?: 120));
    }

    public function twoFactorMode(): string
    {
        $mode = (string) (Setting::query()->where('key', 'two_factor_mode')->value('value') ?: 'off');

        return in_array($mode, ['off', 'optional', 'required'], true) ? $mode : 'off';
    }

    public function ipRestrictionEnabled(): bool
    {
        return $this->flag('ip_restriction_enabled');
    }

    /**
     * @return list<string>
     */
    public function allowedIps(): array
    {
        $raw = (string) Setting::query()->where('key', 'ip_allowlist')->value('value');

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $raw) ?: [])));
    }

    public function ipAllowed(?string $ip): bool
    {
        if (! $this->ipRestrictionEnabled()) {
            return true;
        }

        return $this->listAllows($ip, $this->allowedIps());
    }

    /**
     * @param  list<string>  $rules
     */
    public function listAllows(?string $ip, array $rules): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ($rules as $rule) {
            if ($this->matches($ip, trim($rule))) {
                return true;
            }
        }

        return false;
    }

    private function flag(string $key): bool
    {
        return in_array((string) Setting::query()->where('key', $key)->value('value'), ['1', 'true', 'on'], true);
    }

    private function matches(string $ip, string $rule): bool
    {
        if (! str_contains($rule, '/')) {
            return $ip === $rule;
        }

        [$subnet, $bits] = explode('/', $rule, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $size = (int) $bits;

        if ($ipLong === false || $subnetLong === false || $size < 0 || $size > 32) {
            return false;
        }

        $mask = $size === 0 ? 0 : (-1 << (32 - $size));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
