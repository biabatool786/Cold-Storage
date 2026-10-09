<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user instanceof User && $user->isActiveAccount()) {
            Password::sendResetLink($request->safe()->only('email'));
        }

        return back()->with('status', 'If that email can sign in, a reset link has been sent.');
    }
}
