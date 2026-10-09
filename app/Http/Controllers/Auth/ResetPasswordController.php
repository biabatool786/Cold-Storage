<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->string('email')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset(
            $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request): void {
                if (! $user->isActiveAccount()) {
                    throw ValidationException::withMessages([
                        'email' => 'This account cannot sign in.',
                    ])->redirectTo($this->resetFormUrl($request));
                }

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ])->redirectTo($this->resetFormUrl($request));
        }

        return redirect()->route('login')->with('status', __($status));
    }

    private function resetFormUrl(ResetPasswordRequest $request): string
    {
        return route('password.reset', [
            'token' => $request->input('token'),
            'email' => $request->input('email'),
        ]);
    }
}
