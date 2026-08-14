<?php

namespace App\Enums;

/**
 * A chain a subscription can be paid on.
 *
 * EVM only. Every case shares one JSON-RPC driver because the surface is
 * identical across EVM chains — adding Base, Arbitrum or BSC is a case here
 * plus a config block, with no change to the verifier or the entitlement.
 * A non-EVM chain would need its own driver behind the same contract.
 *
 * Payments an administrator created by hand carry a null network rather than a
 * `Manual` case, so no method here ever has to answer a question about a chain
 * that was never involved.
 */
enum PaymentNetwork: string
{
    case Ethereum = 'ethereum';
    case Arbitrum = 'arbitrum';

    public function label(): string
    {
        $translationKey = "billing.networks.{$this->value}.label";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Ethereum => 'Ethereum',
            self::Arbitrum => 'Arbitrum One',
        };
    }

    public function chainId(): int
    {
        return (int) config("billing.networks.{$this->value}.chain_id", match ($this) {
            self::Ethereum => 1,
            self::Arbitrum => 42161,
        });
    }

    /** The chain's own currency, used when no token contract is involved. */
    public function nativeAsset(): SettlementAsset
    {
        return match ($this) {
            // Arbitrum settles gas and value in ether, same as the chain it
            // rolls up to.
            self::Ethereum, self::Arbitrum => SettlementAsset::Eth,
        };
    }

    /**
     * Whether this chain rolls its transactions up to another one.
     *
     * Only used to explain confirmations honestly. A rollup's blocks arrive in
     * a fraction of a second and are sequenced rather than mined, so a
     * confirmation count here is not the same measure of settlement it is on a
     * layer one — it says the sequencer has accepted the transaction, not that
     * it has reached the base chain.
     */
    public function isRollup(): bool
    {
        return $this === self::Arbitrum;
    }

    public function receivingAddress(): ?string
    {
        $address = config("billing.networks.{$this->value}.address");

        return is_string($address) && $address !== '' ? $this->normalizeAddress($address) : null;
    }

    public function rpcUrl(): ?string
    {
        $url = config("billing.networks.{$this->value}.rpc_url");

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function confirmationsRequired(): int
    {
        return (int) config("billing.networks.{$this->value}.confirmations", 12);
    }

    public function explorerTxUrl(string $txHash): string
    {
        $base = (string) config("billing.networks.{$this->value}.explorer_tx_url", '');

        return $base === '' ? '' : $base.$this->normalizeTxHash($txHash);
    }

    /**
     * The shape a transaction hash must have before it is allowed anywhere near
     * an outbound request.
     *
     * Anchored and lowercase-only because {@see self::normalizeTxHash()} runs
     * first — a pattern that accepted mixed case would let two spellings of one
     * hash past the unique index that stops a payment being claimed twice.
     */
    public function txHashPattern(): string
    {
        return '/^0x[0-9a-f]{64}$/';
    }

    public function normalizeTxHash(string $raw): string
    {
        $hash = mb_strtolower(trim($raw));

        return str_starts_with($hash, '0x') ? $hash : '0x'.$hash;
    }

    /**
     * EIP-55 checksum casing is a display convention, not part of the address,
     * so every comparison happens lowercased on both sides.
     */
    public function normalizeAddress(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }

    /**
     * @return array<int, SettlementAsset>
     */
    public function assets(): array
    {
        return SettlementAsset::availableOn($this);
    }

    /**
     * Whether this chain can currently take a payment.
     *
     * All four conditions matter: a network switched on without an address, an
     * RPC endpoint or a payable asset would hand the buyer an intent nobody can
     * ever settle.
     */
    public function isEnabled(): bool
    {
        return (bool) config("billing.networks.{$this->value}.enabled", false)
            && $this->receivingAddress() !== null
            && $this->rpcUrl() !== null
            && $this->assets() !== [];
    }

    /**
     * @return array<int, self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $network): bool => $network->isEnabled(),
        ));
    }
}
