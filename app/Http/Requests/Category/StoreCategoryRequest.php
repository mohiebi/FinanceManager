<?php

namespace App\Http\Requests\Category;

use App\Enums\TransactionType;
use App\Models\Category;
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
                $name = (string) $this->input('name');

                if (! $type || $name === '') {
                    return;
                }

                $exists = Category::query()
                    ->availableFor($this->user())
                    ->where('type', $type)
                    ->where('slug', Category::slugForName($name))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', __('finance.categories.already_exists'));
                }
            },
        ];
    }

    /**
     * @return array{type: TransactionType, name: string, color: string|null}
     */
    public function categoryData(): array
    {
        return [
            'type' => TransactionType::from($this->validated('type')),
            'name' => (string) $this->validated('name'),
            'color' => $this->validated('color') ?: null,
        ];
    }
}
