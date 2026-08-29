/**
 * The portfolio breakdown, computed in the browser.
 *
 * A deliberate port of App\Actions\Investments\BuildPortfolioBreakdown: with the
 * vault armed the server cannot read a single quantity or cost basis, so the
 * sums, averages and profit/loss have to happen here from decrypted values.
 *
 * Prices and exchange rates are public market data, not user secrets, so they
 * arrive as props (see BuildPortfolioBreakdown::clientPayload) and cost nothing
 * in privacy. Only the holdings themselves are ever ciphertext.
 */
// Relative, with the extension: this module is asserted directly by
// `node --test`, which has no bundler to resolve the `@/` alias for it.
import { convert } from './money.ts';
import type { CurrencyCode, Rates } from './money.ts';

export type PortfolioAssetMeta = {
    id: number;
    key: string;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    unit: string;
    asset_class: string | null;
    /** The asset whose market this one really tracks; null when it is its own. */
    underlying_asset_id: number | null;
    underlying_label: string | null;
    underlying_unit: string | null;
    /** Units of the underlying one unit of this asset is worth, when known. */
    underlying_ratio: number | null;
    /** Current unit price, in toman. */
    price: number;
    price_available: boolean;
};

/**
 * Applies the sign a stored quantity has to carry, given the kind of entry.
 *
 * The twin of `InvestmentKind::signFor()` on the server, and it exists for the
 * same reason: holdings are a plain sum, so a disposal is only a subtraction
 * because its quantity is stored negative.
 *
 * The browser needs its own copy because with the vault armed the quantity is
 * sealed before it is sent, and a server that cannot read a value cannot sign
 * it — so for those users this is the only place the sign can be applied at all.
 */
export function signedQuantityFor(
    kind: 'buy' | 'sell',
    quantity: number,
): number {
    const magnitude = Math.abs(quantity);

    // `magnitude !== 0` keeps a zero from coming back as `-0`, which is equal to
    // zero everywhere it is summed but is not the same value to a strict compare,
    // and would read as a negative in anything that serialises it.
    return kind === 'sell' && magnitude !== 0 ? -magnitude : magnitude;
}

/** One holding, already decrypted. A disposal carries a negative quantity. */
export type PortfolioEntry = {
    investment_asset_id: number;
    kind: 'buy' | 'sell';
    quantity: number;
    cost_basis: number | null;
    cost_basis_currency: string | null;
    sale_price: number | null;
    sale_price_currency: string | null;
};

export type PortfolioAsset = {
    id: number;
    key: string;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    unit: string;
    asset_class: string | null;
    /**
     * The market this row is exposed to — its own id when it is its own market,
     * so grouping on it needs no special case for the roots.
     */
    exposure_id: number;
    underlying_asset_id: number | null;
    underlying_label: string | null;
    underlying_unit: string | null;
    underlying_ratio: number | null;
    quantity: number;
    current_price: number;
    current_price_formatted: string;
    current_value: number;
    current_value_formatted: string;
    price_available: boolean;
    avg_cost_basis: number | null;
    avg_cost_basis_formatted: string | null;
    total_cost: number | null;
    total_cost_formatted: string | null;
    realised_pnl: number | null;
    realised_pnl_formatted: string | null;
    realised_pnl_is_positive: boolean | null;
    pnl: number | null;
    pnl_formatted: string | null;
    pnl_percent: number | null;
    pnl_is_positive: boolean | null;
    entries_count: number;
};

export type PortfolioSummary = {
    total_current_value: number;
    total_current_value_formatted: string;
    total_cost_basis: number;
    total_cost_basis_formatted: string;
    total_pnl: number | null;
    total_pnl_formatted: string | null;
    total_pnl_percent: number | null;
    total_pnl_is_positive: boolean | null;
    has_cost_basis_data: boolean;
    total_realised_pnl: number | null;
    total_realised_pnl_formatted: string | null;
    total_realised_pnl_is_positive: boolean | null;
    has_realised_data: boolean;
    asset_count: number;
};

export type PortfolioBreakdown = {
    assets: PortfolioAsset[];
    summary: PortfolioSummary;
};

/** PHP's number_format($value, $decimals, '.', ','). */
function numberFormat(value: number, decimals: number): string {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

/** Mirrors BuildPortfolioBreakdown::formatter — input is always toman. */
export function formatToman(
    amount: number,
    target: CurrencyCode,
    rates: Rates,
): string {
    return target === 'toman'
        ? numberFormat(amount, 0)
        : numberFormat(convert(amount, 'toman', target, rates), 2);
}

function round(value: number, decimals: number): number {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
}

function toToman(
    amount: number,
    currency: string | null,
    rates: Rates,
): number {
    if (currency === null || currency === '' || currency === 'toman') {
        return amount;
    }

    return convert(amount, currency as CurrencyCode, 'toman', rates);
}

/**
 * What a disposal actually made, in toman.
 *
 * Derived rather than stored, mirroring BuildPortfolioBreakdown::realisedGain:
 * both operands are frozen on the row at sale time, so a later price move cannot
 * rewrite a gain the user has already banked.
 */
function realisedGain(entry: PortfolioEntry, rates: Rates): number {
    const units = Math.abs(entry.quantity);
    const soldFor = toToman(
        entry.sale_price ?? 0,
        entry.sale_price_currency,
        rates,
    );
    const paid =
        entry.cost_basis === null
            ? 0
            : toToman(entry.cost_basis, entry.cost_basis_currency, rates);

    return (soldFor - paid) * units;
}

export function buildBreakdown(
    entries: readonly PortfolioEntry[],
    assetMeta: readonly PortfolioAssetMeta[],
    target: CurrencyCode,
    rates: Rates,
): PortfolioBreakdown {
    const fmt = (amount: number): string => formatToman(amount, target, rates);
    const metaById = new Map(assetMeta.map((asset) => [asset.id, asset]));

    const grouped = new Map<number, PortfolioEntry[]>();

    for (const entry of entries) {
        const bucket = grouped.get(entry.investment_asset_id);

        if (bucket === undefined) {
            grouped.set(entry.investment_asset_id, [entry]);
        } else {
            bucket.push(entry);
        }
    }

    const assets: PortfolioAsset[] = [];
    let totalCurrentValue = 0;
    let totalCurrentValueForCostUnits = 0;
    let totalCostBasis = 0;
    let totalRealised = 0;
    let hasCostBasisData = false;
    let hasRealisedData = false;

    for (const [assetId, assetEntries] of grouped) {
        const meta = metaById.get(assetId);

        if (meta === undefined) {
            continue;
        }

        const totalQuantity = assetEntries.reduce(
            (carry, entry) => carry + entry.quantity,
            0,
        );
        const currentValue = meta.price * totalQuantity;

        const withCostBasis = assetEntries.filter(
            (entry) => entry.cost_basis !== null,
        );
        const costBasisQuantity = withCostBasis.reduce(
            (carry, entry) => carry + entry.quantity,
            0,
        );

        const totalCostBasisInToman = withCostBasis.reduce((carry, entry) => {
            let costPerUnit = entry.cost_basis ?? 0;
            const from = entry.cost_basis_currency;

            if (from !== null && from !== '' && from !== 'toman') {
                costPerUnit = convert(
                    costPerUnit,
                    from as CurrencyCode,
                    'toman',
                    rates,
                );
            }

            return carry + costPerUnit * entry.quantity;
        }, 0);

        const averageCostBasis =
            costBasisQuantity > 0
                ? totalCostBasisInToman / costBasisQuantity
                : null;

        const currentValueForCostUnits = meta.price * costBasisQuantity;

        const pnl =
            costBasisQuantity > 0
                ? currentValueForCostUnits - totalCostBasisInToman
                : null;

        const pnlPercent =
            totalCostBasisInToman > 0 && pnl !== null
                ? round((pnl / totalCostBasisInToman) * 100, 2)
                : null;

        if (costBasisQuantity > 0) {
            hasCostBasisData = true;
            totalCurrentValueForCostUnits += currentValueForCostUnits;
            totalCostBasis += totalCostBasisInToman;
        }

        const disposals = assetEntries.filter(
            (entry) => entry.kind === 'sell' && entry.sale_price !== null,
        );

        const realised = disposals.reduce(
            (carry, entry) => carry + realisedGain(entry, rates),
            0,
        );

        if (disposals.length > 0) {
            hasRealisedData = true;
            totalRealised += realised;
        }

        assets.push({
            id: meta.id,
            key: meta.key,
            label: meta.label,
            icon: meta.icon,
            icon_svg: meta.icon_svg,
            color: meta.color,
            unit: meta.unit,
            asset_class: meta.asset_class,
            exposure_id: meta.underlying_asset_id ?? meta.id,
            underlying_asset_id: meta.underlying_asset_id,
            underlying_label: meta.underlying_label,
            underlying_unit: meta.underlying_unit,
            underlying_ratio: meta.underlying_ratio,
            quantity: round(totalQuantity, 8),
            current_price: meta.price,
            current_price_formatted: fmt(meta.price),
            current_value: currentValue,
            current_value_formatted: fmt(currentValue),
            price_available: meta.price_available,
            avg_cost_basis: averageCostBasis,
            avg_cost_basis_formatted:
                averageCostBasis !== null ? fmt(averageCostBasis) : null,
            total_cost: costBasisQuantity > 0 ? totalCostBasisInToman : null,
            total_cost_formatted:
                costBasisQuantity > 0 ? fmt(totalCostBasisInToman) : null,
            realised_pnl: disposals.length > 0 ? realised : null,
            realised_pnl_formatted:
                disposals.length > 0 ? fmt(Math.abs(realised)) : null,
            realised_pnl_is_positive:
                disposals.length > 0 ? realised >= 0 : null,
            pnl,
            pnl_formatted: pnl !== null ? fmt(Math.abs(pnl)) : null,
            pnl_percent: pnlPercent,
            pnl_is_positive: pnl !== null ? pnl >= 0 : null,
            entries_count: assetEntries.length,
        });

        totalCurrentValue += currentValue;
    }

    assets.sort((left, right) => right.current_value - left.current_value);

    const totalPnl = hasCostBasisData
        ? totalCurrentValueForCostUnits - totalCostBasis
        : null;
    const totalPnlPercent =
        hasCostBasisData && totalCostBasis > 0 && totalPnl !== null
            ? round((totalPnl / totalCostBasis) * 100, 2)
            : null;

    return {
        assets,
        summary: {
            total_current_value: totalCurrentValue,
            total_current_value_formatted: fmt(totalCurrentValue),
            total_cost_basis: totalCostBasis,
            total_cost_basis_formatted: fmt(totalCostBasis),
            total_pnl: totalPnl,
            total_pnl_formatted:
                totalPnl !== null ? fmt(Math.abs(totalPnl)) : null,
            total_pnl_percent: totalPnlPercent,
            total_pnl_is_positive: totalPnl !== null ? totalPnl >= 0 : null,
            has_cost_basis_data: hasCostBasisData,
            // Kept apart from total_pnl on purpose: one is money already banked,
            // the other moves with the market. Summing them means nothing.
            total_realised_pnl: hasRealisedData ? totalRealised : null,
            total_realised_pnl_formatted: hasRealisedData
                ? fmt(Math.abs(totalRealised))
                : null,
            total_realised_pnl_is_positive: hasRealisedData
                ? totalRealised >= 0
                : null,
            has_realised_data: hasRealisedData,
            asset_count: assets.length,
        },
    };
}

export type PortfolioSnapshot = {
    net_worth_formatted: string;
    pnl_percent: number | null;
    pnl_is_positive: boolean | null;
    pnl_formatted: string | null;
    has_cost_basis_data: boolean;
    asset_count: number;
    top_assets: {
        key: string;
        label: string;
        color: string;
        icon: string | null;
        icon_svg: string | null;
        value_formatted: string;
        share: number;
    }[];
};

/** Mirrors BuildPortfolioBreakdown::snapshot — the dashboard's compact card. */
export function buildSnapshot(
    breakdown: PortfolioBreakdown,
): PortfolioSnapshot | null {
    const { assets, summary } = breakdown;

    if (assets.length === 0) {
        return null;
    }

    const totalValue = summary.total_current_value;

    return {
        net_worth_formatted: summary.total_current_value_formatted,
        pnl_percent: summary.total_pnl_percent,
        pnl_is_positive: summary.total_pnl_is_positive,
        pnl_formatted: summary.total_pnl_formatted,
        has_cost_basis_data: summary.has_cost_basis_data,
        asset_count: summary.asset_count,
        top_assets: assets.slice(0, 3).map((asset) => ({
            key: asset.key,
            label: asset.label,
            color: asset.color,
            icon: asset.icon,
            icon_svg: asset.icon_svg,
            value_formatted: asset.current_value_formatted,
            share:
                totalValue > 0
                    ? Math.round((asset.current_value / totalValue) * 100)
                    : 0,
        })),
    };
}

export type PortfolioExposureMember = {
    id: number;
    label: string;
    slug: string;
    quantity: number;
    unit: string;
    value: number;
    value_formatted: string;
    underlying_ratio: number | null;
    price_available: boolean;
};

export type PortfolioExposure = {
    exposure_id: number;
    label: string;
    asset_class: string | null;
    value: number;
    value_formatted: string;
    percent: number | null;
    equivalent_quantity: number | null;
    equivalent_unit: string | null;
    is_held_directly: boolean;
    members: PortfolioExposureMember[];
};

export type PortfolioAssetClass = {
    key: string;
    value: number;
    value_formatted: string;
    percent: number | null;
    asset_count: number;
    exposure_count: number;
};

export type ExposureBreakdown = {
    exposures: PortfolioExposure[];
    classes: PortfolioAssetClass[];
    has_unpriced_assets: boolean;
};

/**
 * Collapses holdings into the markets they are actually bets on.
 *
 * A deliberate port of App\Actions\Investments\BuildExposureBreakdown, and it
 * exists for the same reason the rest of this module does: with the vault armed
 * the server never sees a quantity, so "these four rows are all gold" can only
 * be worked out here.
 *
 * Rolls up value, never quantity — a half coin is counted in coins and bullion
 * in grams. The equivalent quantity is the exception, and only where every
 * member states its conversion.
 */
export function buildExposureBreakdown(
    assets: readonly PortfolioAsset[],
    fmt: (amount: number) => string,
): ExposureBreakdown {
    const total = assets.reduce(
        (carry, asset) => carry + asset.current_value,
        0,
    );
    const hasUnpricedAssets = assets.some((asset) => !asset.price_available);
    const share = (value: number): number | null =>
        hasUnpricedAssets || total <= 0
            ? null
            : round((value / total) * 100, 2);

    const byExposure = new Map<number, PortfolioAsset[]>();

    for (const asset of assets) {
        const bucket = byExposure.get(asset.exposure_id);

        if (bucket === undefined) {
            byExposure.set(asset.exposure_id, [asset]);
        } else {
            bucket.push(asset);
        }
    }

    const exposures: PortfolioExposure[] = [];

    for (const [exposureId, members] of byExposure) {
        // The row that *is* the market, when it is held directly. When it is not
        // — gold owned only as coins — the members all name it, so the label
        // comes from the link instead.
        const root = members.find((asset) => asset.id === exposureId);
        const viaLink = members.find(
            (asset) =>
                asset.underlying_label !== null &&
                asset.underlying_label !== '',
        );
        const value = members.reduce(
            (carry, asset) => carry + asset.current_value,
            0,
        );

        exposures.push({
            exposure_id: exposureId,
            label: root?.label ?? viaLink?.underlying_label ?? '',
            asset_class: root?.asset_class ?? members[0]?.asset_class ?? null,
            value,
            value_formatted: fmt(value),
            percent: share(value),
            equivalent_quantity: equivalentQuantity(members),
            equivalent_unit: root?.unit ?? viaLink?.underlying_unit ?? null,
            is_held_directly: root !== undefined,
            members: members.map((asset) => ({
                id: asset.id,
                label: asset.label,
                slug: asset.key,
                quantity: asset.quantity,
                unit: asset.unit,
                value: asset.current_value,
                value_formatted: asset.current_value_formatted,
                underlying_ratio: asset.underlying_ratio,
                price_available: asset.price_available,
            })),
        });
    }

    exposures.sort((left, right) => right.value - left.value);

    const byClass = new Map<string, PortfolioAsset[]>();

    for (const asset of assets) {
        const key = asset.asset_class ?? 'unclassified';
        const bucket = byClass.get(key);

        if (bucket === undefined) {
            byClass.set(key, [asset]);
        } else {
            bucket.push(asset);
        }
    }

    const classes: PortfolioAssetClass[] = [];

    for (const [key, members] of byClass) {
        const value = members.reduce(
            (carry, asset) => carry + asset.current_value,
            0,
        );

        classes.push({
            key,
            value,
            value_formatted: fmt(value),
            percent: share(value),
            asset_count: members.length,
            exposure_count: new Set(members.map((asset) => asset.exposure_id))
                .size,
        });
    }

    classes.sort((left, right) => right.value - left.value);

    return { exposures, classes, has_unpriced_assets: hasUnpricedAssets };
}

/**
 * How much of the underlying a group adds up to, in the underlying's unit.
 *
 * Null unless every member can be converted — a group where one bar states its
 * gram weight and another does not would otherwise report a total that looks
 * complete and is not. A directly held root counts as itself.
 */
function equivalentQuantity(members: readonly PortfolioAsset[]): number | null {
    let total = 0;

    for (const member of members) {
        if (member.underlying_asset_id === null) {
            total += member.quantity;

            continue;
        }

        if (member.underlying_ratio === null) {
            return null;
        }

        total += member.quantity * member.underlying_ratio;
    }

    return round(total, 8);
}
