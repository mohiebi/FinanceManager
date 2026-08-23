<?php

namespace App\Support;

use Throwable;

/**
 * Reads which market a price formula is actually built on.
 *
 * A custom asset priced as `goldprice * 900 / 750 * 8.133 / 2` already states,
 * structurally, that it is gold — the user just has no way to say so. This turns
 * that into the exposure link the AI needs, both as a suggestion in the form and
 * as the one-off backfill for assets created before the column existed.
 *
 * Deliberately price-independent: it works off the shape of the formula, never a
 * live quote, so it gives the same answer during a migration as it does when the
 * market is open.
 */
class AssetFormulaReferences
{
    /**
     * Formula variable to the asset slug it is a quote for.
     *
     * The variable namespace is wider than the asset table — the same metal is
     * reachable as a per-gram price, an ounce price, or a purity-adjusted one —
     * so several names collapse onto one asset. `coin` is here because a Bahar
     * Azadi is gold: there is no coin row to point at, and pointing it at gold
     * is the more truthful answer anyway.
     *
     * @var array<string, string>
     */
    private const VARIABLE_ASSETS = [
        'gold' => 'gold',
        'goldprice' => 'gold',
        'gold_750' => 'gold',
        'gold_900' => 'gold',
        'gold_ounce' => 'gold',
        'coin' => 'gold',
        'silver' => 'silver',
        'silver_bar' => 'silver',
        'silver_ounce' => 'silver',
        'silver_ounce_usd' => 'silver',
        'usd' => 'usd',
        'usdt' => 'usd',
        'eur' => 'eur',
        'bitcoin' => 'bitcoin',
        'bitcoin_usd' => 'bitcoin',
    ];

    public function __construct(private readonly SafeFormulaEvaluator $formulaEvaluator) {}

    /**
     * Every variable name the formula mentions, in order of first appearance.
     *
     * @return list<string>
     */
    public function variablesIn(string $formula): array
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $formula, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * The distinct assets the formula draws on.
     *
     * @return list<string>
     */
    public function assetSlugsIn(string $formula): array
    {
        $slugs = [];

        foreach ($this->variablesIn($formula) as $variable) {
            $slug = self::VARIABLE_ASSETS[mb_strtolower($variable)] ?? null;

            if ($slug !== null && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * The one asset a formula tracks, or null when it tracks none or several.
     *
     * A formula mixing gold and the dollar has no single underlying, and
     * guessing one would file the holding under a market it is only half
     * exposed to. Null is the honest answer, and leaves the field for the user.
     */
    public function soleAssetSlugIn(string $formula): ?string
    {
        $slugs = $this->assetSlugsIn($formula);

        return count($slugs) === 1 ? $slugs[0] : null;
    }

    /**
     * How many units of the underlying one unit of this asset is worth.
     *
     * Recovered by evaluating the formula with the underlying's price set to 1,
     * which is exact for any formula that is linear in that one variable — the
     * shape every weight-and-purity formula takes. `goldprice * 900 / 750 *
     * 8.133 / 2` comes back as 4.88 grams of gold per half coin.
     *
     * Null when the formula names more than one market, or is not linear, or
     * cannot be evaluated at all.
     */
    public function ratioToUnderlying(string $formula): ?float
    {
        $slug = $this->soleAssetSlugIn($formula);

        if ($slug === null) {
            return null;
        }

        $variables = $this->variablesIn($formula);
        $atOne = $this->evaluateWith($formula, $variables, 1.0);

        if ($atOne === null || $atOne <= 0.0) {
            return null;
        }

        // Linearity check: a formula that is a constant multiple of the
        // underlying doubles when the underlying does. Anything that does not —
        // a formula with an added constant, or one dividing by the price — would
        // yield a "ratio" that silently changes with the market.
        $atTwo = $this->evaluateWith($formula, $variables, 2.0);

        if ($atTwo === null || abs($atTwo - ($atOne * 2)) > max(1e-6, abs($atOne) * 1e-9)) {
            return null;
        }

        return round($atOne, 8);
    }

    /**
     * @param  list<string>  $variables
     */
    private function evaluateWith(string $formula, array $variables, float $value): ?float
    {
        try {
            return $this->formulaEvaluator->evaluate(
                $formula,
                array_fill_keys($variables, $value),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
