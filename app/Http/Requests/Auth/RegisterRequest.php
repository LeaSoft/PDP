<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\CompanyEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                // Accounts are for company addresses only (same check as profile email changes and Google sign-in)
                CompanyEmail::rule(),
                'unique:'.User::class,
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'Registration is only permitted from a work email address '.CompanyEmail::label().'.',
        ];
    }
}
