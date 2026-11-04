<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Two-factor auth is switched off in config/fortify.php, so there is no challenge view
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }
}
