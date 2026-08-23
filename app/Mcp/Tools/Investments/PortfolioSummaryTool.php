<?php

namespace App\Mcp\Tools\Investments;

use App\Actions\Investments\BuildExposureBreakdown;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Models\User;
use App\Support\CurrencyPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the user\'s portfolio breakdown: per-asset holdings with current value, cost basis, and profit/loss, plus overall totals. Also returns "exposures" (holdings rolled up by the market they actually track, so a half gold coin counts as gold) and "classes" (rolled up by family, e.g. metal or currency) — use those, not the per-asset rows, to judge concentration and diversification. Values can be reported in toman, usd, or eur (defaults to the user\'s preferred currency).')]
class PortfolioSummaryTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Portfolio];
    }

    public function __construct(
        private readonly BuildPortfolioBreakdown $buildPortfolioBreakdown,
        private readonly BuildExposureBreakdown $buildExposureBreakdown,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'currency' => ['nullable', 'string', 'in:toman,usd,eur'],
        ]);

        $currency = CurrencyPreference::resolveFor($user, $validated['currency'] ?? null);
        $entries = $this->buildPortfolioBreakdown->entriesFor($user);

        if ($entries->isEmpty()) {
            return Response::structured([
                'assets' => [],
                'exposures' => [],
                'classes' => [],
                'summary' => null,
                'message' => 'No investments recorded yet.',
            ]);
        }

        $breakdown = $this->buildPortfolioBreakdown->handle($entries, $currency);
        $exposure = $this->buildExposureBreakdown->handle(
            $breakdown['assets'],
            $this->buildPortfolioBreakdown->formatter($currency),
        );

        return Response::structured([
            'currency' => $currency->value,
            // Named on the tin so a model reading this does not have to infer it:
            // several rows here can be the same bet.
            'grouping_note' => 'Each row in "assets" is one holding, not one market. Two rows can track the same market — a half gold coin and bullion are both gold. Judge concentration from "exposures" and "classes".',
            'assets' => collect($breakdown['assets'])->map(fn (array $asset): array => [
                'asset' => $asset['label'],
                'slug' => $asset['key'],
                'unit' => $asset['unit'],
                'asset_class' => $asset['asset_class'],
                'tracks' => $asset['underlying_label'],
                'units_of_tracked_asset_each' => $asset['underlying_ratio'],
                'quantity' => $asset['quantity'],
                'current_price' => $asset['current_price_formatted'],
                'current_value' => $asset['current_value_formatted'],
                'avg_cost_basis' => $asset['avg_cost_basis_formatted'],
                'total_cost' => $asset['total_cost_formatted'],
                'pnl' => $asset['pnl_formatted'],
                'pnl_percent' => $asset['pnl_percent'],
                'pnl_is_positive' => $asset['pnl_is_positive'],
                'entries_count' => $asset['entries_count'],
            ])->all(),
            'exposures' => collect($exposure['exposures'])->map(fn (array $group): array => [
                'market' => $group['label'],
                'asset_class' => $group['asset_class'],
                'value' => $group['value_formatted'],
                'percent_of_portfolio' => $group['percent'],
                // Only present where every member states its conversion — for a
                // metals holder this is the number they actually think in.
                'total_units' => $group['equivalent_quantity'],
                'unit' => $group['equivalent_unit'],
                'held_as' => collect($group['members'])->map(fn (array $member): array => [
                    'asset' => $member['label'],
                    'quantity' => $member['quantity'],
                    'unit' => $member['unit'],
                    'value' => $member['value_formatted'],
                ])->all(),
            ])->all(),
            'classes' => collect($exposure['classes'])->map(fn (array $group): array => [
                'class' => $group['key'],
                'label' => $group['label'],
                'value' => $group['value_formatted'],
                'percent_of_portfolio' => $group['percent'],
                'asset_count' => $group['asset_count'],
                'distinct_markets' => $group['exposure_count'],
            ])->all(),
            // Percentages are null when this is true: some holding has no price,
            // so every share would be measured against an understated total.
            'has_unpriced_assets' => $exposure['has_unpriced_assets'],
            'summary' => [
                'total_value' => $breakdown['summary']['total_current_value_formatted'],
                'total_cost_basis' => $breakdown['summary']['total_cost_basis_formatted'],
                'total_pnl' => $breakdown['summary']['total_pnl_formatted'],
                'total_pnl_percent' => $breakdown['summary']['total_pnl_percent'],
                'total_pnl_is_positive' => $breakdown['summary']['total_pnl_is_positive'],
                'asset_count' => $breakdown['summary']['asset_count'],
            ],
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency to report values in. Defaults to the user\'s preferred currency.'),
        ];
    }
}
