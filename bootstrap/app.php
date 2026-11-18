<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureDatabaseIsMigrated;
use App\Http\Middleware\EnsurePasswordLoginEnabled;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Outside the web group on purpose: a check every minute must not start a
        // session (they live in the database), set cookies or render Inertia.
        // No throttle either: SELECT 1 costs less than the limiter's own cache writes.
        then: function () {
            Route::get('/api/health', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureDatabaseIsMigrated::class,
        ]);

        // Register route middleware aliases
        $middleware->alias([
            'super_admin' => EnsureSuperAdmin::class,
            'password.login' => EnsurePasswordLoginEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
