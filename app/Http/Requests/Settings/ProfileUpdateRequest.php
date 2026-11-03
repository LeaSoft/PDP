<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                // Existing non-company addresses can be kept, but a new address must be a company one
                Rule::when($this->input('email') !== $this->user()->email, RegisterRequest::COMPANY_EMAIL_RULE),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.regex' => 'Email can only be changed to a work address @leasoft.org.',
        ];
    }
}
