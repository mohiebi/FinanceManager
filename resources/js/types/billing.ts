export type BillingPlanKey = 'monthly' | 'quarterly' | 'yearly';
export type PaymentNetworkKey = 'ethereum' | 'arbitrum';
export type SettlementAssetKey = 'eth' | 'usdt' | 'usdc';

export type PaymentStatusKey =
    | 'pending'
    | 'submitted'
    | 'confirmed'
    | 'failed'
    | 'quarantined'
    | 'expired'
    /** The buyer withdrew the intent, as opposed to letting its window close. */
    | 'cancelled'
    | 'refunded';

export type PaymentTone = 'positive' | 'pending' | 'negative' | 'neutral';

/**
 * The user's paid-plan state, shared on every authenticated page.
 *
 * Kept separate from the `features` map on purpose: that answers "which modules
 * may this user use", which stays true for everyone while no module is Pro.
 * This answers "has this user paid", which the billing page and any upgrade
 * prompt need regardless.
 */
export type SubscriptionState = {
    is_pro: boolean;
    /** ISO 8601. Stays populated after lapsing, so it also reads as "was Pro until". */
    pro_until: string | null;
    /** False switches the billing page and its settings nav entry off entirely. */
    billing_enabled: boolean;
};

export type PlanCard = {
    key: BillingPlanKey;
    label: string;
    description: string;
    months: number;
    /** Decimal strings throughout — never numbers. See App\Support\Billing\TokenAmount. */
    price_usd: string;
    per_month_usd: string;
    highlighted: boolean;
    /** Null on the plan that sets the baseline. */
    savings_percent: number | null;
};

export type AssetOption = {
    key: SettlementAssetKey;
    label: string;
    symbol: string;
    /** Null for the chain's native currency, which has no contract. */
    contract: string | null;
    decimals: number;
    display_precision: number;
    is_stable: boolean;
};

export type NetworkOption = {
    key: PaymentNetworkKey;
    label: string;
    chain_id: number;
    available: boolean;
    available_addresses: number;
    confirmations_required: number;
    assets: AssetOption[];
};

/**
 * The rail this buyer used last, derived from their payment history rather than
 * stored as a setting. Null for somebody who has never opened an intent, and
 * ignored by the page if either side is no longer on offer.
 */
export type PreferredRail = {
    network: PaymentNetworkKey;
    asset: SettlementAssetKey;
};

export type CouponKindKey = 'percent' | 'fixed';

/** What a coupon is worth against one plan. */
export type CouponPlanPrice = {
    list_price_usd: string;
    discount_usd: string;
    final_price_usd: string;
    /** True when nothing is left to pay, so redeeming skips the chain entirely. */
    covers_everything: boolean;
};

export type CouponPreview = {
    code: string;
    plans: Record<string, CouponPlanPrice>;
};

/**
 * Flashed once, on the redirect that follows redeeming a full-price coupon.
 *
 * A coupon that covers everything never opens a payment intent, so there is no
 * `PaymentRecord` to show and nothing in the history until the grant lands.
 * This is what the page confirms with instead.
 */
export type ActivationReceipt = {
    plan_label: string;
    months: number;
    coupon_code: string;
};

export type CouponPreviewResponse =
    | { accepted: true; code: string; plans: Record<string, CouponPlanPrice> }
    | { accepted: false; message: string };

export type AdminCoupon = {
    id: number;
    code: string;
    kind: CouponKindKey;
    kind_label: string;
    percent_off: number | null;
    /** Decimal string, like every other money value here. */
    amount_off_usd: string | null;
    /** Null means anybody may redeem it. */
    user_email: string | null;
    max_redemptions: number | null;
    max_per_user: number | null;
    /** Reserved plus consumed — released claims went back into the pool. */
    claimed_count: number;
    consumed_count: number;
    /** Only a code nobody ever touched may be deleted rather than disabled. */
    deletable: boolean;
    valid_until: string | null;
    disabled: boolean;
    expired: boolean;
    note: string | null;
    created_at: string;
};

/**
 * One row of the buyer's subscription history.
 *
 * Not every entry is a payment. A coupon covering the whole price opens no
 * intent — a chain cannot carry a zero transfer — so those arrive as their own
 * kind rather than being missing from the list that is supposed to explain how
 * the account came to be Pro.
 */
export type HistoryEntry = {
    id: string;
    kind: 'payment' | 'coupon';
    /**
     * The plan's name — null on a coupon entry, which the grant records only as
     * a month count. The page titles those from `months` instead: doing it
     * server-side would need Laravel's `:count`, and this group is rendered by
     * vue-i18n, which interpolates `{count}`.
     */
    plan_label: string | null;
    months: number;
    status_label: string;
    tone: PaymentTone;
    price_usd: string;
    /** The undiscounted price, or null when no coupon was involved. */
    list_price_usd: string | null;
    coupon_code: string | null;
    explorer_url: string | null;
    failure_message: string | null;
    created_at: string;
    /** When the months actually landed, or null while nothing has settled. */
    settled_at: string | null;
};

export type PaymentRecord = {
    id: string;
    status: PaymentStatusKey;
    status_label: string;
    tone: PaymentTone;
    plan: BillingPlanKey;
    plan_label: string;
    months: number;
    price_usd: string;
    /** Null on a payment an admin created by hand, where no chain was involved. */
    network: PaymentNetworkKey | null;
    network_label: string | null;
    chain_id: number | null;
    asset: SettlementAssetKey;
    asset_symbol: string;
    asset_decimals: number;
    token_contract: string | null;
    pay_to_address: string | null;
    expected_amount: string;
    received_amount: string | null;
    /** The undiscounted price, or null when no coupon was used. */
    list_price_usd: string | null;
    coupon_code: string | null;
    quote_rate: string;
    quote_expires_at: string | null;
    tx_hash: string | null;
    explorer_url: string | null;
    confirmations: number | null;
    confirmations_required: number | null;
    failure_reason: string | null;
    failure_message: string | null;
    screening_risk: 'no_match' | 'unknown' | 'flagged' | 'sanctioned' | null;
    /** An EIP-681 request a wallet can open with the amount already filled in. */
    payment_uri: string | null;
    created_at: string;
    expires_at: string;
    verified_at: string | null;
};
