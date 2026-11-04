<?php

namespace App\Support;

/**
 * Which ways of signing in are switched on.
 */
final class AuthFeatures
{
    public static function passwordLogin(): bool
    {
        return (bool) config('auth.password_login', true);
    }

    /**
     * Google sign-in is on only once the OAuth client is configured, so a
     * deploy without the keys simply hides the button.
     */
    public static function googleLogin(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
