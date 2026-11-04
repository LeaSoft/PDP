<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\CompanyEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $emailChanged = $this->input('email') !== $user->email;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
                // Existing non-company addresses can be kept, but a new address must be a company one
                Rule::when($emailChanged, CompanyEmail::rule()),
                // A Google-linked account keeps the address Google signed it in with
                function (string $attribute, mixed $value, Closure $fail) use ($user, $emailChanged) {
                    if ($emailChanged && $user->google_id !== null) {
                        $fail('Email is managed by your Google account and cannot be changed here.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'Email can only be changed to a work address '.CompanyEmail::label().'.',
        ];
    }
}
