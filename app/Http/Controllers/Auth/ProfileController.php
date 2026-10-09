<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => request()->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->safe()->only('name', 'email', 'phone');

        if ($request->filled('password')) {
            $attributes['password'] = $request->validated('password');
        }

        $user->update($attributes);

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }

    public function startTwoFactor(Totp $totp): RedirectResponse
    {
        $secret = $totp->secret();
        request()->user()->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('two_factor_secret', $secret);
    }

    public function confirmTwoFactor(Request $request, Totp $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $secret = $request->user()->two_factor_secret;
        if (! $secret || ! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'That authenticator code is not valid.']);
        }

        $request->user()->update(['two_factor_confirmed_at' => now()]);

        return back()->with('status', 'Two-factor authentication is on.');
    }
}
