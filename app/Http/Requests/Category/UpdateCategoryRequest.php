<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use App\Support\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && $this->user() !== null
            && $category->user_id !== null
            && (int) $category->user_id === (int) $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
                $category = $this->route('category');

                if (! $category instanceof Category || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $errors = CategoryRules::errors(
                    $this->user(),
                    $category->type,
                    $this->forBothTypes($category),
                    $this->parentId($category),
                    (string) $this->input('name'),
                    $category,
                );

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }

    /**
     * @return array{name: string, color: string|null, parent_id: int|null, for_both_types: bool}
     */
    public function categoryData(): array
    {
        $category = $this->route('category');
        assert($category instanceof Category);

        return [
            'name' => (string) $this->validated('name'),
            'color' => $this->validated('color') ?: null,
            'parent_id' => $this->parentId($category),
            'for_both_types' => $this->forBothTypes($category),
        ];
    }

    /**
     * Absent means unchanged: the colour swatch and the inline rename patch
     * only the fields they own, and must not quietly reset the rest.
     */
    private function parentId(Category $category): ?int
    {
        if (! $this->exists('parent_id')) {
            return $category->parent_id;
        }

        return $this->filled('parent_id') ? (int) $this->input('parent_id') : null;
    }

    private function forBothTypes(Category $category): bool
    {
        return $this->exists('for_both_types')
            ? $this->boolean('for_both_types')
            : $category->for_both_types;
    }
}
