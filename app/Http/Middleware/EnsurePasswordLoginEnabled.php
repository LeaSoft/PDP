<?php

namespace App\Http\Middleware;

use App\Support\AuthFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes password routes (login, registration, reset, change) when the app is Google-only.
 */
class EnsurePasswordLoginEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(AuthFeatures::passwordLogin(), 404);

        return $next($request);
    }
}
