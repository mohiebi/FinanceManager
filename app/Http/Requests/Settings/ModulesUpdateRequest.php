<?php

namespace App\Http\Requests\Settings;

use App\Enums\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModulesUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only toggleable features are accepted, so core modules are rejected here
            // rather than needing a separate guard in the controller.
            'feature' => ['required', 'string', Rule::in(array_column(Feature::toggleable(), 'value'))],
            'enabled' => ['required_without:show_promo', 'boolean'],
            'show_promo' => ['required_without:enabled', 'boolean'],
        ];
    }

    public function feature(): Feature
    {
        return Feature::from($this->validated('feature'));
    }
}
