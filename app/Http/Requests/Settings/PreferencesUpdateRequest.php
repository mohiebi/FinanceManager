<?php

namespace App\Http\Requests\Settings;

use App\Support\FrontendLocalization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesUpdateRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::in(FrontendLocalization::locales())],
            'calendar' => ['required', 'string', Rule::in(FrontendLocalization::calendars())],
        ];
    }
}
