<?php

namespace App\Enums;

/**
 * A currency a subscription can be paid in.
 *
 * Named *settlement* rather than *payment* asset to keep it clear of
 * {@see AssetType} and the InvestmentAsset model, which describe what the user
 * holds. This describes how they pay us, and never enters their finance data.
 *
 * Decimals and contract addresses are read per network, never hardcoded here:
 * USDT is 6 decimals on Ethereum and 18 on BSC, and treating them as one number
 * would misread every amount by a factor of a million.
 */
enum SettlementAsset: string
{
    case Eth = 'eth';
    case Usdt = 'usdt';
    case Usdc = 'usdc';

    public function symbol(): string
    {
        return match ($this) {
            self::Eth => 'ETH',
            self::Usdt => 'USDT',
            self::Usdc => 'USDC',
        };
    }

    public function label(): string
    {
        $translationKey = "billing.assets.{$this->value}.label";
        $translatedLabel = __($translationKey);

        return $translatedLabel !== $translationKey ? $translatedLabel : $this->symbol();
    }

    /** Whether this is the chain's own currency rather than a token contract. */
    public function isNative(): bool
    {
        return $this === self::Eth;
    }

    /**
     * Whether one unit is worth one dollar.
     *
     * Decides both whether a rate lookup is needed at all and how long the
     * quoted amount stays payable.
     */
    public function isStable(): bool
    {
        return ! $this->isNative();
    }

    public function decimalsOn(PaymentNetwork $network): int
    {
        return (int) config("billing.networks.{$network->value}.assets.{$this->value}.decimals", 18);
    }

    /** Null for a native asset, which has no contract to transfer through. */
    public function contractOn(PaymentNetwork $network): ?string
    {
        $contract = config("billing.networks.{$network->value}.assets.{$this->value}.contract");

        return is_string($contract) && $contract !== '' ? mb_strtolower($contract) : null;
    }

    public function isAvailableOn(PaymentNetwork $network): bool
    {
        $configured = config("billing.networks.{$network->value}.assets.{$this->value}");

        if (! is_array($configured)) {
            return false;
        }

        // A token with no contract configured is not payable — there would be
        // nothing to check a Transfer log against.
        return $this->isNative() || $this->contractOn($network) !== null;
    }

    /**
     * How long a quoted amount in this asset stays payable.
     *
     * We carry the price risk for the whole window, so a volatile asset gets a
     * short one and a stablecoin gets a day.
     */
    public function quoteLockMinutes(): int
    {
        return (int) ($this->isStable()
            ? config('billing.quote.lock_minutes.stable', 1440)
            : config('billing.quote.lock_minutes.volatile', 30));
    }

    /** Decimal places worth showing a human. Not the same as on-chain precision. */
    public function displayPrecision(): int
    {
        return $this->isStable() ? 2 : 6;
    }

    /**
     * Decimal places the converted price is quantized to.
     *
     * Everything below this is left as zeros so the per-payment nonce has room
     * to occupy them. Two places is a dollar figure; eight is fine for ether,
     * where one unit at that precision is worth a small fraction of a cent.
     */
    public function quotePrecision(): int
    {
        return $this->isStable() ? 2 : 8;
    }

    /**
     * The decimal place the nonce's least significant digit sits at.
     *
     * Sits strictly below {@see self::quotePrecision()}, giving four free digits
     * — ten thousand distinguishable amounts per price — and strictly above the
     * asset's own precision, so the nonce is representable on-chain.
     */
    public function noncePrecision(): int
    {
        return $this->quotePrecision() + 4;
    }

    /**
     * @return array<int, self>
     */
    public static function availableOn(PaymentNetwork $network): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $asset): bool => $asset->isAvailableOn($network),
        ));
    }
}
