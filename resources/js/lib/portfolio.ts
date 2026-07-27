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
    /** Current unit price, in toman. */
    price: number;
    price_available: boolean;
};

/** One holding, already decrypted. */
export type PortfolioEntry = {
    investment_asset_id: number;
    quantity: number;
    cost_basis: number | null;
    cost_basis_currency: string | null;
};

export type PortfolioAsset = {
    id: number;
    key: string;
    label: string;
    icon: string | null;
    icon_svg: string | null;
    color: string;
    unit: string;
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
    let hasCostBasisData = false;

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

        assets.push({
            id: meta.id,
            key: meta.key,
            label: meta.label,
            icon: meta.icon,
            icon_svg: meta.icon_svg,
            color: meta.color,
            unit: meta.unit,
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
