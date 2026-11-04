<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\CompanyEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;

/**
 * Turns a Google identity into a PDP user: finds the linked account, links an
 * existing account with the same company email, or creates a new one.
 */
class ResolveGoogleUser
{
    /**
     * @throws GoogleSignInRejected
     */
    public function handle(GoogleUser $google): User
    {
        $raw = $google->getRaw();
        $googleId = (string) $google->getId();
        $email = Str::lower(trim((string) $google->getEmail()));

        if ($googleId === '' || $email === '') {
            throw new GoogleSignInRejected('Google did not share your account details. Please try again.');
        }

        // The `hd` we send to Google only filters the account picker and can be stripped from the URL,
        // so the decision is made here from what Google returned.
        if (filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            throw new GoogleSignInRejected('Your Google email address is not verified.');
        }

        // `hd` is present only for accounts managed by a Google Workspace; a personal Google account
        // registered on a company address has no `hd` and is rejected here.
        if (! CompanyEmail::isCompanyDomain($raw['hd'] ?? null) || ! CompanyEmail::isCompany($email)) {
            throw new GoogleSignInRejected('Only work Google accounts '.CompanyEmail::label().' can sign in.');
        }

        $linked = User::where('google_id', $googleId)->first();

        if ($linked) {
            return $linked;
        }

        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing) {
            if ($existing->google_id !== null) {
                throw new GoogleSignInRejected('This PDP account is linked to a different Google account.');
            }

            return $this->link($existing, $googleId);
        }

        return $this->create($google, $googleId, $email);
    }

    /**
     * First Google sign-in into an account that already exists. The account may have been
     * created by someone else (password registration never proved the mailbox), so every
     * other way into it is closed: other sessions and remember-me cookies stop working.
     */
    private function link(User $user, string $googleId): User
    {
        $user->forceFill([
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        return $user;
    }

    private function create(GoogleUser $google, string $googleId, string $email): User
    {
        $user = new User;

        // Roles are set explicitly: the first sign-in never grants any rights
        $user->forceFill([
            'name' => Str::limit(trim((string) $google->getName()) ?: Str::before($email, '@'), 255, ''),
            'email' => $email,
            'google_id' => $googleId,
            'email_verified_at' => now(),
            // Nobody knows this password; the account opens with Google only
            'password' => Str::random(64),
            'is_moderator' => false,
            'super_admin' => false,
        ])->save();

        event(new Registered($user));

        return $user;
    }
}
