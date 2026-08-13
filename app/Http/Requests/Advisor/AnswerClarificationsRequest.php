<?php

namespace App\Http\Requests\Advisor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnswerClarificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'between:1,3'],
            'answers.*' => ['required'],
            'accepted_assets' => ['nullable', 'array', 'max:3'],
            'accepted_assets.*.asset_key' => ['required', 'string', 'max:80', 'distinct'],
            'accepted_assets.*.name' => ['required', 'string', 'max:120'],
            'accepted_assets.*.category' => ['required', 'in:stock,etf,bond,currency,metal,crypto,commodity,real_estate,private_asset,other'],
        ];
    }
}
