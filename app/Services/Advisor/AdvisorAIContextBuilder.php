<?php

namespace App\Services\Advisor;

use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Actions\Transactions\CurrencyConverter;
use App\Contracts\AdvisorKnowledgeProvider;
use App\Enums\AdvisorRecommendationMode;
use App\Enums\Currency;
use App\Models\AdvisorProfile;
use App\Models\User;
use App\Support\CurrencyPreference;
use App\Support\FrontendLocalization;
use Illuminate\Support\Arr;

class AdvisorAIContextBuilder
{
    public function __construct(
        private readonly BuildPortfolioBreakdown $portfolioBreakdown,
        private readonly CurrencyConverter $currencyConverter,
        private readonly AdvisorKnowledgeProvider $knowledgeProvider,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, AdvisorProfile $profile): array
    {
        $profile->loadMissing('assessment.answers');
        $payload = is_array($profile->profile_payload) ? $profile->profile_payload : [];
        $scores = is_array($payload['scores'] ?? null) ? $payload['scores'] : [];
        $goals = is_array($payload['goals'] ?? null) ? $payload['goals'] : [];
        $vaultArmed = $user->vaultIsArmed();
        $mode = $vaultArmed ? AdvisorRecommendationMode::TargetOnly : AdvisorRecommendationMode::Rebalance;
        $currentPortfolio = null;
        $newCapital = null;

        if (! $vaultArmed) {
            [$currentPortfolio, $newCapital] = $this->readablePortfolioContext($user, $profile, $payload);
        }

        $context = [
            'context_version' => 1,
            'recommendation_mode' => $mode->value,
            // Part of the hashed context on purpose: a recommendation written in
            // the wrong language is a different answer, so switching the
            // preference has to invalidate the cached one rather than leave the
            // user with English prose they cannot read.
            'response_language' => FrontendLocalization::languageName($user->locale),
            'investor_profile' => [
                'persona' => $payload['persona'] ?? null,
                'risk_band' => $payload['risk_band'] ?? null,
                ...$scores,
                'time_horizon' => $goals['time_horizon'] ?? null,
                'maximum_tolerated_drawdown' => $payload['maximum_tolerated_drawdown'] ?? null,
            ],
            'financial_context' => $payload['financial_context'] ?? [],
            'loss_context' => $payload['loss_context'] ?? [],
            'goals' => $goals,
            'portfolio_preferences' => $this->portfolioPreferences($payload),
            'constraints' => $payload['constraints'] ?? null,
            'selected_assets' => $payload['selected_assets'] ?? null,
            'options_capability' => $payload['options_capability'] ?? null,
            'current_portfolio' => $currentPortfolio,
            'new_capital' => $newCapital,
            'warnings' => $payload['warnings'] ?? [],
        ];
        $context['knowledge_context'] = $this->knowledgeProvider->contextFor($context);
        $context['knowledge_mode'] = $context['knowledge_context']['mode'];

        return $context;
    }

    /** @param array<string, mixed> $profilePayload
     * @return array<string, mixed>
     */
    private function portfolioPreferences(array $profilePayload): array
    {
        $preferences = is_array($profilePayload['portfolio_preferences'] ?? null)
            ? $profilePayload['portfolio_preferences']
            : [];

        return Arr::only($preferences, [
            'scope',
            'primary_currency',
            'country',
            'markets',
            'tax_sensitive',
        ]);
    }

    /**
     * @param  array<string, mixed>  $profilePayload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>|null}
     */
    private function readablePortfolioContext(User $user, AdvisorProfile $profile, array $profilePayload): array
    {
        $requestedCurrency = Currency::tryFrom(mb_strtolower((string) ($profilePayload['portfolio_preferences']['primary_currency'] ?? '')));
        $currency = $requestedCurrency ?? CurrencyPreference::resolveFor($user);
        $breakdown = $this->portfolioBreakdown->handle($this->portfolioBreakdown->entriesFor($user), $currency);
        $totalValue = (float) $breakdown['summary']['total_current_value'];
        $selectedAssetsById = collect($profilePayload['selected_assets'] ?? [])
            ->filter(fn (mixed $asset): bool => is_array($asset) && filled($asset['investment_asset_id'] ?? null))
            ->keyBy('investment_asset_id');
        $holdings = collect($breakdown['assets'])->map(function (array $asset) use ($currency, $selectedAssetsById, $totalValue): array {
            $selected = $selectedAssetsById->get($asset['id']);
            $priceAvailable = (bool) $asset['price_available'];

            return [
                'asset_key' => $selected['asset_key'] ?? null,
                'investment_asset_id' => $asset['id'],
                'name' => $asset['label'],
                'quantity' => $asset['quantity'],
                'current_value' => $priceAvailable ? $this->fromToman((float) $asset['current_value'], $currency) : null,
                'current_percent' => $priceAvailable && $totalValue > 0 ? round(((float) $asset['current_value'] / $totalValue) * 100, 2) : null,
                'cost_basis' => $asset['total_cost'] === null ? null : $this->fromToman((float) $asset['total_cost'], $currency),
                'price_available' => $priceAvailable,
                'selected_for_target' => $selected !== null,
            ];
        })->values()->all();
        $hasUnpricedAssets = collect($holdings)->contains(fn (array $holding): bool => ! $holding['price_available']);

        if ($hasUnpricedAssets) {
            $holdings = array_map(function (array $holding): array {
                $holding['current_percent'] = null;

                return $holding;
            }, $holdings);
        }

        $preferencesAnswer = $profile->assessment->answers
            ->firstWhere('question_key', 'portfolio_preferences')?->answer;
        $newAmount = is_array($preferencesAnswer) ? $preferencesAnswer['new_investable_amount'] ?? null : null;

        return [[
            'base_currency' => $currency->value,
            'total_value' => $hasUnpricedAssets ? null : $this->fromToman($totalValue, $currency),
            'priced_subtotal' => $this->fromToman($totalValue, $currency),
            'holdings' => $holdings,
            'has_unpriced_assets' => $hasUnpricedAssets,
        ], $newAmount === null ? null : [
            'amount' => (float) $newAmount,
            'currency' => $currency->value,
            'recurring_contribution' => (float) ($preferencesAnswer['recurring_contribution'] ?? 0),
        ]];
    }

    private function fromToman(float $value, Currency $currency): float
    {
        return round($this->currencyConverter->convert($value, Currency::Toman, $currency), 2);
    }
}
