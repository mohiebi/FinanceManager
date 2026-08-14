<?php

namespace App\Http\Requests\Settings;

use App\Enums\BillingPlan;
use App\Enums\PaymentNetwork;
use App\Enums\SettlementAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPaymentRequest extends FormRequest
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
            // Only plans currently priced, and only chains that have an address,
            // an endpoint and a payable asset — the alternative is handing a
            // buyer an intent nobody can ever settle.
            'plan' => ['required', 'string', Rule::in(array_column(BillingPlan::available(), 'value'))],
            'network' => ['required', 'string', Rule::in(array_column(PaymentNetwork::available(), 'value'))],
            // Validated against the chosen network rather than a global list:
            // an asset is a property of a chain, and USDC existing somewhere is
            // no reason to accept it here.
            'asset' => ['required', 'string', Rule::in(array_column($this->assetsForChosenNetwork(), 'value'))],
        ];
    }

    public function plan(): BillingPlan
    {
        return BillingPlan::from($this->validated('plan'));
    }

    public function network(): PaymentNetwork
    {
        return PaymentNetwork::from($this->validated('network'));
    }

    public function asset(): SettlementAsset
    {
        return SettlementAsset::from($this->validated('asset'));
    }

    /**
     * @return array<int, SettlementAsset>
     */
    private function assetsForChosenNetwork(): array
    {
        $network = PaymentNetwork::tryFrom((string) $this->input('network'));

        // An unknown network fails its own rule; returning nothing here just
        // means the asset fails too, rather than being judged against a chain
        // that does not exist.
        return $network === null || ! $network->isEnabled() ? [] : $network->assets();
    }
}
