<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
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
                $name = (string) $this->input('name');

                if (! $category instanceof Category || $name === '') {
                    return;
                }

                $exists = Category::query()
                    ->availableFor($this->user())
                    ->where('type', $category->type)
                    ->where('slug', Category::slugForName($name))
                    ->whereKeyNot($category->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', __('finance.categories.already_exists'));
                }
            },
        ];
    }

    /**
     * @return array{name: string, color: string|null}
     */
    public function categoryData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'color' => $this->validated('color') ?: null,
        ];
    }
}
