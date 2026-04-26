<?php

namespace App\Http\Requests\Transaction;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class TransactionRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = Category::query()->find($this->input('category_id'));
                $transactionType = TransactionType::tryFrom((string) $this->input('type'));

                if (! $category instanceof Category || ! $transactionType) {
                    return;
                }

                if ($category->type !== $transactionType) {
                    $validator->errors()->add('category_id', 'Choose a category for the selected transaction type.');
                }

                if ($category->user_id !== null && (int) $category->user_id !== (int) $this->user()->id) {
                    $validator->errors()->add('category_id', 'Choose one of your categories or a default category.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionData(): array
    {
        return [
            ...$this->validated(),
            'description' => $this->validated('description') ?? null,
        ];
    }
}
