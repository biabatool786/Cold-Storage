<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $invalidated = $user->session_invalidated_at;
        $started = $request->session()->get('authenticated_at');
        if ($invalidated && ($started === null || (int) $started <= $invalidated->getTimestamp())) {
            return $this->end($request, 'Your session was ended by an administrator.');
        }

        $last = $request->session()->get('last_activity');
        if (is_int($last) && (time() - $last) >= ($this->policy->sessionMinutes() * 60)) {
            return $this->end($request, 'Your session timed out.');
        }

        $request->session()->put('last_activity', time());

        return $next($request);
    }

    private function end(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
