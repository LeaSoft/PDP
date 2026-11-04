<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\GoogleSignInRejected;
use App\Actions\Auth\ResolveGoogleUser;
use App\Http\Controllers\Controller;
use App\Support\AuthFeatures;
use App\Support\CompanyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Send the person to Google's account picker.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(AuthFeatures::googleLogin(), 404);

        $parameters = ['prompt' => 'select_account'];

        // Only a hint for the account picker; the callback checks the domain itself
        $domains = CompanyEmail::domains();
        if (count($domains) === 1) {
            $parameters['hd'] = $domains[0];
        }

        return Socialite::driver('google')->with($parameters)->redirect();
    }

    /**
     * Google sends the person back here.
     */
    public function callback(Request $request, ResolveGoogleUser $resolveGoogleUser): RedirectResponse
    {
        abort_unless(AuthFeatures::googleLogin(), 404);

        if ($request->filled('error')) {
            return $this->failed('Google sign-in was cancelled.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->failed('Google sign-in expired. Please try again.');
        } catch (Throwable $e) {
            report($e);

            return $this->failed('Could not sign in with Google. Please try again.');
        }

        try {
            $user = $resolveGoogleUser->handle($googleUser);
        } catch (GoogleSignInRejected $e) {
            return $this->failed($e->getMessage());
        }

        // No remember-me: once Google access is closed, the PDP session runs out on its own
        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function failed(string $message): RedirectResponse
    {
        return to_route('login')->withErrors(['google' => $message]);
    }
}
