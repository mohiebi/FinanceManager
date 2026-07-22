<?php

namespace App\Http\Requests\Transaction;

use App\Support\TransactionRules;
use Illuminate\Foundation\Http\FormRequest;
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
        return TransactionRules::rules();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = TransactionRules::categoryErrors(
                    $this->user(),
                    $this->input('category_id'),
                    $this->input('type'),
                );

                foreach ($errors as $field => $message) {
                    $validator->errors()->add($field, $message);
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
