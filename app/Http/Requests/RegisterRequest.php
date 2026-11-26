<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => $this->str('email')->lower()->toString()]);
        }

        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => $this->str('phone')->replaceMatches('/[\s().-]+/', '')->toString()]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['bail', 'required', 'string', 'regex:/\A\+?[0-9]{7,15}\z/', 'unique:users,phone'],
            'password' => [
                'bail',
                'required',
                'string',
                'confirmed',
                Password::min(8),
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('Use a password of up to 72 bytes without null characters.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a phone number with 7 to 15 digits.'];
    }
}
