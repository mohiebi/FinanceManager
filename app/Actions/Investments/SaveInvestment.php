<?php

namespace App\Actions\Investments;

use App\Enums\Currency;
use App\Enums\InvestmentKind;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use App\Support\Encryption\SealedField;
use Illuminate\Validation\ValidationException;

/**
 * Shared investment-entry validation and persistence used by the web
 * InvestmentController and the MCP tools so both surfaces resolve assets and
 * derive cost basis identically.
 */
class SaveInvestment
{
    /**
     * Rules for recording a disposal.
     *
     * Quantity arrives positive and is negated on the way in — asking the user to
     * type a minus sign to sell something would be a trap.
     *
     * The user enters what they sold for in total, not per unit; the per-unit price
     * is derived here, mirroring how `total_cost` becomes `cost_basis` on the buy
     * side. With the vault armed the browser has to do both that division and the
     * cost-basis lookup, because a server that cannot read a quantity cannot divide
     * by one. Unarmed, the server computes both and ignores what the client claims.
     *
     * @return array<string, mixed>
     */
    public static function sellRules(bool $vaultArmed = false): array
    {
        return [
            'investment_asset_id' => ['nullable', 'integer'],
            'asset_type' => ['nullable', 'string', 'max:100'],
            'quantity' => $vaultArmed
                ? SealedField::rules()
                : ['required', 'numeric', 'min:0.00000001'],
            'total_sale' => $vaultArmed
                ? ['nullable']
                : ['required', 'numeric', 'min:0'],
            'sale_price' => $vaultArmed
                ? SealedField::rules()
                : ['nullable'],
            'sale_price_currency' => ['required', 'string', 'max:10'],
            'cost_basis' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable'],
            'cost_basis_currency' => ['nullable', 'string', 'max:10'],
            'note' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $vaultArmed = false): array
    {
        return [
            'investment_asset_id' => ['nullable', 'integer'],
            'asset_type' => ['nullable', 'string', 'max:100'],
            'quantity' => $vaultArmed
                ? SealedField::rules()
                : ['required', 'numeric', 'min:0.00000001'],
            'total_cost' => $vaultArmed
                ? ['nullable']
                : ['nullable', 'numeric', 'min:0'],
            'cost_basis' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'numeric', 'min:0'],
            'cost_basis_currency' => ['nullable', 'string', 'max:10'],
            'note' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Resolves the asset (by id, falling back to slug) among those available
     * to the user, then derives cost basis from total_cost when provided.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException when no matching asset is available
     */
    public static function normalize(User $user, array $validated, bool $vaultArmed = false): array
    {
        $asset = self::resolveAsset($user, $validated);

        if ($vaultArmed) {
            unset($validated['total_cost']);

            $validated['investment_asset_id'] = $asset->id;
            $validated['asset_type'] = $asset->slug;
            $validated['cost_basis_currency'] = filled($validated['cost_basis'] ?? null)
                ? ($validated['cost_basis_currency'] ?? null)
                : null;

            return SealedField::wrap($validated, ['quantity', 'cost_basis', 'note']);
        }

        $quantity = (float) $validated['quantity'];
        $totalCost = self::nullableFloat($validated['total_cost'] ?? null);
        $costBasis = $totalCost !== null
            ? $totalCost / $quantity
            : self::nullableFloat($validated['cost_basis'] ?? null);

        unset($validated['total_cost']);

        $validated['investment_asset_id'] = $asset->id;
        $validated['asset_type'] = $asset->slug;
        $validated['cost_basis'] = $costBasis;
        $validated['cost_basis_currency'] = $costBasis !== null
            ? ($validated['cost_basis_currency'] ?? null)
            : null;

        return $validated;
    }

    /**
     * Turn a sale into a ledger row: negative quantity, basis frozen at sale time.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException when no matching asset is available, or when the
     *                             user is trying to sell more than they hold
     */
    public static function normalizeSell(
        User $user,
        array $validated,
        bool $vaultArmed = false,
        ?BuildPortfolioBreakdown $breakdownBuilder = null,
    ): array {
        $asset = self::resolveAsset($user, $validated);

        $validated['investment_asset_id'] = $asset->id;
        $validated['asset_type'] = $asset->slug;
        $validated['kind'] = InvestmentKind::Sell;

        if ($vaultArmed) {
            // Holdings and basis are both ciphertext here, so the browser has
            // already done the arithmetic and the over-sell check with it.
            unset($validated['total_sale']);

            return SealedField::wrap(
                $validated,
                ['quantity', 'cost_basis', 'sale_price', 'note'],
            );
        }

        $held = self::heldUnits($user, $asset);
        $units = abs((float) $validated['quantity']);

        if ($units > $held) {
            throw ValidationException::withMessages([
                'quantity' => __('finance.investments.sell_exceeds_holding'),
            ]);
        }

        // Server-side truth: whatever the client sent for the basis is discarded.
        $builder = $breakdownBuilder ?? app(BuildPortfolioBreakdown::class);
        $breakdown = $builder->handle($builder->entriesFor($user), Currency::Toman);

        $averageBasis = collect($breakdown['assets'])
            ->firstWhere('id', $asset->id)['avg_cost_basis'] ?? null;

        $totalSale = self::nullableFloat($validated['total_sale'] ?? null);

        unset($validated['total_sale']);

        $validated['quantity'] = -$units;
        // Stored per unit so a later partial sale of the same asset is comparable,
        // and so realised gain is a plain (sold − paid) × units.
        $validated['sale_price'] = $totalSale === null ? null : $totalSale / $units;
        $validated['cost_basis'] = $averageBasis;
        $validated['cost_basis_currency'] = $averageBasis === null ? null : Currency::Toman->value;

        return $validated;
    }

    /** Net units currently held of one asset. */
    private static function heldUnits(User $user, InvestmentAsset $asset): float
    {
        return (float) $user->investments()
            ->where('investment_asset_id', $asset->id)
            ->get()
            ->sum(fn (Investment $entry): float => (float) $entry->quantity);
    }

    /**
     * @param  array<string, mixed>  $data  normalized investment attributes
     */
    public function create(User $user, array $data): Investment
    {
        return $user->investments()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data  normalized investment attributes
     */
    public function update(Investment $investment, array $data): Investment
    {
        $investment->fill($data)->save();

        return $investment;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function resolveAsset(User $user, array $input): InvestmentAsset
    {
        $query = InvestmentAsset::query()->availableFor($user);

        $asset = null;
        $assetId = $input['investment_asset_id'] ?? null;
        if ($assetId !== null && $assetId !== '') {
            $asset = (clone $query)->whereKey((int) $assetId)->first();
        }

        $assetType = $input['asset_type'] ?? null;
        if (! $asset && $assetType !== null && $assetType !== '') {
            $asset = (clone $query)->where('slug', (string) $assetType)->first();
        }

        if (! $asset) {
            throw ValidationException::withMessages([
                'investment_asset_id' => __('settings.assets.invalid'),
            ]);
        }

        return $asset;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
