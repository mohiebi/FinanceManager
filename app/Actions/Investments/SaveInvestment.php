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
     * Slack allowed when checking a holding against zero, so float drift in a
     * sum of stored quantities cannot make an exactly-empty holding look short.
     */
    private const HOLDING_TOLERANCE = 1e-9;

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

        // Through the enum rather than a bare minus sign, so the rule that a
        // disposal is stored negative lives in exactly one place.
        $validated['quantity'] = InvestmentKind::Sell->signFor($units);
        // Stored per unit so a later partial sale of the same asset is comparable,
        // and so realised gain is a plain (sold − paid) × units.
        $validated['sale_price'] = $totalSale === null ? null : $totalSale / $units;
        $validated['cost_basis'] = $averageBasis;
        $validated['cost_basis_currency'] = $averageBasis === null ? null : Currency::Toman->value;

        return $validated;
    }

    /**
     * Net units currently held of one asset.
     *
     * Rounded like the breakdown the dialog shows, so float drift in the sum
     * (0.2 + 0.7 + 0.1 is just under 1) cannot refuse selling the whole holding.
     */
    private static function heldUnits(User $user, InvestmentAsset $asset): float
    {
        return round($user->investments()
            ->where('investment_asset_id', $asset->id)
            ->get()
            ->sum(fn (Investment $entry): float => (float) $entry->quantity), 8);
    }

    /**
     * @param  array<string, mixed>  $data  normalized investment attributes
     */
    public function create(User $user, array $data): Investment
    {
        return $user->investments()->create($data);
    }

    /**
     * Applies a purchase edit. Disposals are refused.
     *
     * The guard lives here rather than in the callers because there are four of
     * them — the web route, the MCP batch, a confirmed MCP proposal, and the
     * propose tool — and every one of them arrives with a payload shaped by
     * {@see self::normalize()}, which speaks only the buy vocabulary: a positive
     * quantity, and a `total_cost` that becomes the per-unit basis. A sale has
     * none of that. Its quantity is stored negative so holdings stay a plain
     * sum, so letting one through rewrote the sign and moved the holding by
     * twice the size of the sale, while `kind` still read `sell` and the
     * realised gain kept being derived from it.
     *
     * @param  array<string, mixed>  $data  normalized investment attributes
     *
     * @throws ValidationException when the entry is a disposal, or when the edit
     *                             would leave a holding below zero
     */
    public function update(Investment $investment, array $data): Investment
    {
        if ($investment->isSell()) {
            throw ValidationException::withMessages([
                'quantity' => __('finance.investments.sell_not_editable'),
            ]);
        }

        self::assertHoldingStaysPositive($investment, $data);

        $investment->fill($data)->save();

        return $investment;
    }

    /**
     * Refuses an edit that would leave the user holding less than nothing.
     *
     * The sell path already enforces that nobody sells more than they hold, but
     * the same account could be driven negative from the other side — shrink a
     * purchase below what has already been sold against it and the holding goes
     * negative, which the sell path would never have allowed.
     *
     * Both assets are checked, because moving a purchase to a different one
     * drains the asset it left just as surely as shrinking it does.
     *
     * @param  array<string, mixed>  $data  normalized investment attributes
     *
     * @throws ValidationException
     */
    private static function assertHoldingStaysPositive(Investment $investment, array $data): void
    {
        $incoming = $data['quantity'] ?? null;

        // With the vault armed the quantity arrives as ciphertext and the stored
        // ones read back the same way, so the browser owns this check — the same
        // division of labour {@see self::normalizeSell()} already relies on.
        if (! is_numeric($incoming)) {
            return;
        }

        $movingTo = (int) ($data['investment_asset_id'] ?? $investment->investment_asset_id);
        $movingFrom = (int) $investment->investment_asset_id;

        foreach (array_unique([$movingTo, $movingFrom]) as $assetId) {
            $net = self::netHeldExcluding($investment, $assetId);

            if ($net === null) {
                continue;
            }

            if ($assetId === $movingTo) {
                $net += (float) $incoming;
            }

            // A hair below zero rather than zero itself: these are float sums, and
            // selling a holding down to exactly empty must stay legal.
            if ($net < -self::HOLDING_TOLERANCE) {
                throw ValidationException::withMessages([
                    'quantity' => __('finance.investments.update_leaves_negative_holding'),
                ]);
            }
        }
    }

    /**
     * Net units of one asset, ignoring the row being edited.
     *
     * Null when a quantity cannot be read, which is the armed-vault case and the
     * signal to leave the judgement to the browser.
     */
    private static function netHeldExcluding(Investment $investment, int $assetId): ?float
    {
        $net = 0.0;

        $entries = Investment::query()
            ->where('user_id', $investment->user_id)
            ->where('investment_asset_id', $assetId)
            ->whereKeyNot($investment->getKey())
            ->get();

        foreach ($entries as $entry) {
            if (! is_numeric($entry->quantity)) {
                return null;
            }

            $net += (float) $entry->quantity;
        }

        return $net;
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
