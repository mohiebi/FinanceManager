<?php

namespace App\Http\Requests\Category;

use App\Enums\TransactionType;
use App\Support\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['nullable', 'integer'],
            'for_both_types' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = TransactionType::tryFrom((string) $this->input('type'));

                if (! $type || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $errors = CategoryRules::errors(
                    $this->user(),
                    $type,
                    $this->boolean('for_both_types'),
                    $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
                    (string) $this->input('name'),
                );

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * @return array{type: TransactionType, name: string, color: string|null, parent_id: int|null, for_both_types: bool}
     */
    public function categoryData(): array
    {
        return [
            'type' => TransactionType::from($this->validated('type')),
            'name' => (string) $this->validated('name'),
            'color' => $this->validated('color') ?: null,
            'parent_id' => $this->filled('parent_id') ? (int) $this->validated('parent_id') : null,
            'for_both_types' => $this->boolean('for_both_types'),
        ];
    }
}
