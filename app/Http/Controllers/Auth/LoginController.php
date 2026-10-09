<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\PasswordPolicy;
use App\Services\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AccountSecurity $security, PasswordPolicy $policy): RedirectResponse
    {
        $email = (string) $request->validated('email');
        $account = User::query()->where('email', $email)->first();

        if (! $policy->ipAllowed($request->ip())) {
            $security->record('ip_blocked', $account, $email);

            throw ValidationException::withMessages([
                'email' => 'This network is not allowed to sign in.',
            ])->redirectTo(route('login'));
        }

        if ($account?->locked_at !== null) {
            $security->record('failed_login', $account, $email);

            throw ValidationException::withMessages([
                'email' => 'This account is locked.',
            ])->redirectTo(route('login'));
        }

        if (! Auth::attempt($request->safe()->only('email', 'password'), $request->boolean('remember'))) {
            $security->registerFailure($account, $email);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ])->redirectTo(route('login'));
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->isActiveAccount()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account cannot sign in.',
            ])->redirectTo(route('login'));
        }

        if ($user->two_factor_confirmed_at !== null) {
            $request->session()->put('two_factor_user_id', $user->id);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        if ($policy->twoFactorMode() === 'required') {
            $request->session()->regenerate();
            $request->session()->put('authenticated_at', time());
            $security->clearFailures($user);
            $security->record('login', $user);

            return redirect()->route('profile.edit')->with('status', 'Two-factor authentication is required. Confirm an authenticator code.');
        }

        $request->session()->regenerate();
        $request->session()->put('authenticated_at', time());
        $security->clearFailures($user);
        $security->record('login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function challenge(): View
    {
        abort_unless(session()->has('two_factor_user_id'), 403);

        return view('auth.two-factor');
    }

    public function confirmChallenge(Request $request, Totp $totp, AccountSecurity $security): RedirectResponse
    {
        $user = User::query()->find(session('two_factor_user_id'));
        abort_unless($user instanceof User && $user->two_factor_secret, 403);
        $data = $request->validate(['code' => ['required', 'string']]);

        if (! $totp->verify($user->two_factor_secret, $data['code'])) {
            $security->record('failed_login', $user);

            throw ValidationException::withMessages(['code' => 'That authenticator code is not valid.']);
        }

        session()->forget('two_factor_user_id');
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('authenticated_at', time());
        $security->clearFailures($user);
        $security->record('login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AccountSecurity $security): RedirectResponse
    {
        if ($request->user() instanceof User) {
            $security->record('logout', $request->user());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
