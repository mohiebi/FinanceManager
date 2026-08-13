<?php

namespace App\Http\Requests\Advisor;

use App\Support\Encryption\SealedField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SealRecommendationRequest extends FormRequest
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
            'recommendation_payload' => SealedField::rules(),
            'output_hash' => ['required', 'string', 'size:64'],
        ];
    }
}
