<?php

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class CompleteSignupRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signup_token' => 'required|string',
            'name' => 'required|string|max:255',
            'birthdate' => 'required|date|before:today',
            'password' => $this->passwordRules(),
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
