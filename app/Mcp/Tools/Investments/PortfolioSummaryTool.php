<?php

namespace App\Mcp\Tools\Investments;

use App\Actions\Investments\BuildPortfolioBreakdown;
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
#[Description('Get the user\'s portfolio breakdown: per-asset holdings with current value, cost basis, and profit/loss, plus overall totals. Values can be reported in toman, usd, or eur (defaults to the user\'s preferred currency).')]
class PortfolioSummaryTool extends Tool
{
    public function __construct(private readonly BuildPortfolioBreakdown $buildPortfolioBreakdown) {}

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
                'summary' => null,
                'message' => 'No investments recorded yet.',
            ]);
        }

        $breakdown = $this->buildPortfolioBreakdown->handle($entries, $currency);

        return Response::structured([
            'currency' => $currency->value,
            'assets' => collect($breakdown['assets'])->map(fn (array $asset): array => [
                'asset' => $asset['label'],
                'slug' => $asset['key'],
                'unit' => $asset['unit'],
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
