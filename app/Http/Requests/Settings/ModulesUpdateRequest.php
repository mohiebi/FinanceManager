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
            // Core modules and self-managed ones are both rejected here rather than
            // needing a guard in the controller. The self-managed case matters most:
            // a PATCH that armed the vault would null the server's copy of the data
            // key with nothing wrapped in its place — permanent, total data loss.
            'feature' => ['required', 'string', Rule::in(array_column(Feature::directlyToggleable(), 'value'))],
            'enabled' => ['required_without:show_promo', 'boolean'],
            'show_promo' => ['required_without:enabled', 'boolean'],
        ];
    }

    public function feature(): Feature
    {
        return Feature::from($this->validated('feature'));
    }
}
