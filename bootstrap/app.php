<?php

use App\Http\Middleware\EnforceIpAllowlist;
use App\Http\Middleware\EnforceSessionTimeout;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResetTenantContext;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('csms:scan-overdue-invoices')->dailyAt('06:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'session.timeout' => EnforceSessionTimeout::class,
            'ip.allowlist' => EnforceIpAllowlist::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
        ]);

        $middleware->prepend(ResetTenantContext::class);
        $middleware->appendToGroup('web', ResolveTenant::class);
        $middleware->appendToGroup('api', ResolveTenant::class);
        $middleware->appendToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);
        $middleware->appendToPriorityList(AuthenticatesRequests::class, EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
