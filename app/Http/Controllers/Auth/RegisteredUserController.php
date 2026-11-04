<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\AuthFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response|RedirectResponse
    {
        // Google-only: new accounts are created on the first Google sign-in
        if (! AuthFeatures::passwordLogin()) {
            return to_route('login');
        }

        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $validated = $request->validated();

        $user = $registerUser->handle(
            $validated['name'],
            $validated['email'],
            $validated['password']
        );

        Auth::login($user);

        return to_route('dashboard');
    }
}
