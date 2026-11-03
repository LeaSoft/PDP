<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    // Accounts are for company addresses only (also enforced when the email changes in profile settings)
    public const COMPANY_EMAIL_RULE = 'regex:/^[^@]+@leasoft\.org$/i';

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
                self::COMPANY_EMAIL_RULE,
                'unique:' . User::class,
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'Registration is only permitted from a work email address @leasoft.org.',
        ];
    }
}
