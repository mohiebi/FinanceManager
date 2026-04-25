<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CodeVerificationRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email|max:255',
            'code' => 'required|string|digits:6',
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
