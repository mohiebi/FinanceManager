<?php

namespace App\Mcp\Tools\Investments;

use App\Enums\AssetClass;
use App\Enums\Feature;
use App\Enums\InvestmentAssetPriceSource;
use App\Mcp\Concerns\RequiresFeature;
use App\Mcp\Support\ProposalService;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Propose creating a custom investment asset with a manually set price or a price formula. URL-based price feeds cannot be created through AI assistants. This does NOT change any data: it returns a proposal_id — show it to the user, get approval, then call confirm-proposal.')]
class ProposeCustomAssetTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Investments];
    }

    public function __construct(private readonly ProposalService $proposals) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        // URL-based sources (json/xml) are deliberately excluded: AI clients
        // must not be able to point price fetching at arbitrary URLs.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'price_source_type' => ['required', Rule::in([
                InvestmentAssetPriceSource::Manual->value,
                InvestmentAssetPriceSource::Formula->value,
            ])],
            'price' => ['required_if:price_source_type,manual', 'nullable', 'numeric', 'min:0'],
            'formula' => ['required_if:price_source_type,formula', 'nullable', 'string', 'max:500'],
            'asset_class' => ['nullable', Rule::enum(AssetClass::class)],
            'tracks_asset_slug' => ['nullable', 'string', 'max:100'],
            'units_of_tracked_asset_each' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $name = trim($validated['name']);

        $exists = InvestmentAsset::query()
            ->availableFor($user)
            ->where('slug', InvestmentAsset::slugForName($name))
            ->exists();

        if ($exists) {
            return Response::error('An asset with this name already exists.');
        }

        $underlying = $this->resolveUnderlying($user, $validated['tracks_asset_slug'] ?? null);

        if ($underlying === false) {
            return Response::error('tracks_asset_slug must name an existing asset that does not itself track another asset.');
        }

        $sourceType = $validated['price_source_type'];

        $payload = [
            'name' => $name,
            'slug' => InvestmentAsset::slugForName($name),
            'unit' => trim($validated['unit']),
            'asset_class' => $validated['asset_class'] ?? null,
            'underlying_asset_id' => $underlying?->id,
            'underlying_ratio' => $underlying === null
                ? null
                : ($validated['units_of_tracked_asset_each'] ?? null),
            'color' => '#02CD86',
            'price_source_type' => $sourceType,
            'price_source_config' => $sourceType === InvestmentAssetPriceSource::Manual->value
                ? ['price' => (float) $validated['price']]
                : ['formula' => trim((string) $validated['formula'])],
        ];

        $proposal = $this->proposals->propose(
            $user,
            'create',
            'investment_asset',
            null,
            $payload,
            $this->proposals->diff([
                'name' => $payload['name'],
                'unit' => $payload['unit'],
                'asset_class' => $payload['asset_class'],
                'tracks' => $underlying?->label(),
                'price_source_type' => $payload['price_source_type'],
                'price_source_config' => $payload['price_source_config'],
            ]),
        );

        return $this->proposals->toResponse($proposal);
    }

    /**
     * The named underlying, null when none was given, or false when the slug
     * names nothing usable — kept distinct so "not stated" cannot be mistaken
     * for "stated and wrong".
     */
    private function resolveUnderlying(User $user, ?string $slug): InvestmentAsset|false|null
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        $underlying = InvestmentAsset::query()
            ->availableFor($user)
            ->where('slug', $slug)
            ->first();

        if ($underlying === null || ! $underlying->canBeUnderlying()) {
            return false;
        }

        return $underlying;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name of the new asset, e.g. "Company stock".')->required(),
            'unit' => $schema->string()->description('Unit of measurement, e.g. shares, grams.')->required(),
            'price_source_type' => $schema->string()->enum(['manual', 'formula'])->description('How the price is determined: a manually set price or a formula over other asset prices.')->required(),
            'price' => $schema->number()->description('For manual pricing: the current price per unit in toman.'),
            'formula' => $schema->string()->description('For formula pricing: an expression over other asset slugs, e.g. "gold * 4.6".'),
            'asset_class' => $schema->string()->enum(AssetClass::values())->description('The family this asset belongs to, e.g. metal for a gold coin or a silver bar.'),
            'tracks_asset_slug' => $schema->string()->description('Slug of the asset whose market this one really follows, e.g. "gold" for a half gold coin or "silver" for a silver bar. Set this whenever the new asset is a form of an asset that already exists, so the portfolio counts them as one exposure. Must name an asset that does not itself track another.'),
            'units_of_tracked_asset_each' => $schema->number()->description('Optional. How many units of the tracked asset one unit of this asset is worth, e.g. 4.6 grams of gold per half coin. Lets the portfolio report a total in the unit of the tracked asset.'),
        ];
    }
}
