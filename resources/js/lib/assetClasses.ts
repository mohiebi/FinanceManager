/**
 * The asset-class vocabulary, mirroring App\Enums\AssetClass.
 *
 * Kept here rather than passed as a prop from every page that offers the field:
 * it is a closed list that changes only when the enum does, and a second copy in
 * each controller would be one more place for the two to drift.
 */
export const ASSET_CLASSES = [
    'metal',
    'currency',
    'crypto',
    'stock',
    'etf',
    'bond',
    'commodity',
    'real_estate',
    'private_asset',
    'other',
] as const;

export type AssetClass = (typeof ASSET_CLASSES)[number];

/**
 * A first guess from the unit the user typed, used to preselect the field.
 *
 * The twin of AssetClass::guessFromUnit(). Only ever a default in a form the
 * user can still change — nothing is decided on the strength of it.
 */
export function guessAssetClassFromUnit(unit: string): AssetClass {
    const normalised = unit.trim().toLowerCase();

    if (
        [
            'g',
            'gr',
            'gram',
            'grams',
            'oz',
            'ounce',
            'kg',
            'coin',
            'coins',
            'bar',
            'bars',
            'مثقال',
            'گرم',
            'سکه',
            'شمش',
        ].includes(normalised)
    ) {
        return 'metal';
    }

    if (
        ['usd', 'eur', 'gbp', 'aed', 'try', 'toman', 'rial'].includes(
            normalised,
        )
    ) {
        return 'currency';
    }

    if (['btc', 'eth', 'usdt', 'sol'].includes(normalised)) {
        return 'crypto';
    }

    if (['share', 'shares', 'stock', 'stocks', 'سهم'].includes(normalised)) {
        return 'stock';
    }

    return 'other';
}

/**
 * The formula variables that name a market, and the asset slug each one quotes.
 *
 * The twin of App\Support\AssetFormulaReferences::VARIABLE_ASSETS. Lets the form
 * offer the exposure a formula already states — a price written as
 * `goldprice * 4.6` is gold, and the user should not have to say so twice.
 */
const FORMULA_VARIABLE_ASSETS: Record<string, string> = {
    gold: 'gold',
    goldprice: 'gold',
    gold_750: 'gold',
    gold_900: 'gold',
    gold_ounce: 'gold',
    coin: 'gold',
    silver: 'silver',
    silver_bar: 'silver',
    silver_ounce: 'silver',
    silver_ounce_usd: 'silver',
    usd: 'usd',
    usdt: 'usd',
    eur: 'eur',
    bitcoin: 'bitcoin',
    bitcoin_usd: 'bitcoin',
};

/**
 * The one asset slug a formula tracks, or null when it names none or several.
 *
 * A formula mixing gold and the dollar has no single underlying, and guessing
 * one would file the holding under a market it is only half exposed to.
 */
export function soleAssetSlugInFormula(formula: string): string | null {
    const slugs = new Set<string>();

    for (const token of formula.match(/[A-Za-z_][A-Za-z0-9_]*/g) ?? []) {
        const slug = FORMULA_VARIABLE_ASSETS[token.toLowerCase()];

        if (slug !== undefined) {
            slugs.add(slug);
        }
    }

    return slugs.size === 1 ? [...slugs][0] : null;
}
