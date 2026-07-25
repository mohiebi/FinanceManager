<?php

namespace App\Mcp\Tools\Investments;

use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\CalendarDates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s investment entries (newest first) and the investment assets available to them. Each entry records a quantity of an asset acquired on a date, optionally with its cost basis.')]
class ListInvestmentsTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Investments];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $isJalali = CalendarDates::isJalaliUser($user);

        // Quantities and cost basis are encrypted at rest, so all shaping
        // happens in PHP on a bounded result set.
        $entries = $user->investments()
            ->with('asset')
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Investment $investment): array => [
                'id' => $investment->id,
                'asset_id' => $investment->asset?->id,
                'asset' => $investment->asset?->label() ?? $investment->asset_type,
                'asset_slug' => $investment->asset?->slug ?? $investment->asset_type,
                'unit' => $investment->asset?->unit,
                'quantity' => (float) $investment->quantity,
                'cost_basis' => $investment->cost_basis !== null ? (float) $investment->cost_basis : null,
                'cost_basis_currency' => $investment->cost_basis_currency,
                'note' => $investment->note,
                'occurred_at' => $investment->occurred_at->toDateString(),
                'occurred_at_jalali' => $isJalali
                    ? CalendarDates::toJalali($investment->occurred_at)
                    : null,
            ]);

        $assets = InvestmentAsset::query()
            ->availableFor($user)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (InvestmentAsset $asset): array => [
                'id' => $asset->id,
                'slug' => $asset->slug,
                'label' => $asset->label(),
                'unit' => $asset->unit,
                'is_default' => $asset->is_default,
            ]);

        return Response::structured([
            'calendar' => $isJalali ? 'jalali' : 'gregorian',
            'entries' => $entries->all(),
            'available_assets' => $assets->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
