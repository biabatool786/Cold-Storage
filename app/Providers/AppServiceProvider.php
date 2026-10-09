<?php

namespace App\Providers;

use App\Services\Security\PasswordPolicy;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => app(PasswordPolicy::class)->rule());
    }
}
