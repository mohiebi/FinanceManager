<?php

namespace App\Http\Requests\Settings;

use App\Models\SubscriptionPayment;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment instanceof SubscriptionPayment
            && $this->user() !== null
            && $payment->user_id === $this->user()->getKey();
    }

    /**
     * Normalize before validating, so the buyer is not told off for pasting a
     * hash the way a block explorer displays it.
     */
    protected function prepareForValidation(): void
    {
        $payment = $this->route('payment');
        $hash = (string) $this->input('tx_hash');

        if ($payment instanceof SubscriptionPayment && $payment->network !== null && trim($hash) !== '') {
            $this->merge(['tx_hash' => $payment->network->normalizeTxHash($hash)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $payment = $this->route('payment');

        // The pattern lives on the network because it is a property of the
        // chain, and because normalization has already run — a rule that
        // accepted mixed case would let two spellings of one hash past the
        // unique index that stops a transaction being claimed twice.
        $pattern = $payment instanceof SubscriptionPayment && $payment->network !== null
            ? $payment->network->txHashPattern()
            : '/^$/';

        return [
            'tx_hash' => ['required', 'string', 'max:80', 'regex:'.$pattern],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tx_hash.regex' => __('billing.pay.hash_invalid'),
        ];
    }
}
