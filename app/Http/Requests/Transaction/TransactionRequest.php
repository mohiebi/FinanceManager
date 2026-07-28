<?php

namespace App\Http\Requests\Transaction;

use App\Support\Encryption\SealedField;
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
        return TransactionRules::rules($this->user()->vaultIsArmed());
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
        $data = [
            ...$this->validated(),
            'description' => $this->validated('description') ?? null,
        ];

        if (! $this->user()->vaultIsArmed()) {
            return $data;
        }

        // Wrapped so the cast stores the browser's ciphertext verbatim instead of
        // trying to encrypt it again with a key the server no longer has.
        return SealedField::wrap($data, ['amount', 'title', 'description']);
    }
}
