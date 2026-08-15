<?php

namespace App\Http\Requests\Advisor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentSectionRequest extends FormRequest
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
            'answers' => ['required', 'array'],
            /*
             * Set when the user is stepping backwards. The section is kept as a
             * draft rather than being completed, so leaving a page half-answered
             * no longer costs them the answers they had already given.
             */
            'partial' => ['sometimes', 'boolean'],
        ];
    }
}
