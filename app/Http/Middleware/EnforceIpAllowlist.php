<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIpAllowlist
{
    public function __construct(private readonly PasswordPolicy $policy) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $this->policy->ipRestrictionEnabled() || $this->policy->ipAllowed($request->ip())) {
            return $next($request);
        }

        if ($user->isSuperAdmin() && $request->routeIs('settings.*')) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'This network is not allowed to sign in.']);
    }
}
