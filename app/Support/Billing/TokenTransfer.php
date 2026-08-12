<?php

namespace App\Support\Billing;

use Carbon\CarbonImmutable;

/**
 * What a chain says about one transaction, reduced to the facts a payment cares
 * about.
 *
 * The driver produces this; the verifier judges it. Splitting them that way is
 * what lets every verification rule be tested without a node, and what lets a
 * second chain be added without the rules moving.
 */
final readonly class TokenTransfer
{
    public function __construct(
        public string $txHash,
        /** False when the transaction reverted — it is on the chain, it just did nothing. */
        public bool $succeeded,
        public int $blockNumber,
        public CarbonImmutable $blockTimestamp,
        public int $confirmations,
        public ?string $fromAddress,
        /**
         * The transaction's own recipient, which is a contract whenever the
         * payment was routed through one rather than sent directly.
         */
        public ?string $txTo,
        /**
         * Base units that actually reached the payment's address, in the
         * payment's own asset. Zero when nothing did.
         */
        public string $creditedAmount,
        /** Whether the receipt carried logs at all, i.e. whether a contract ran. */
        public bool $touchedContract,
        /**
         * Whether some *other* token was transferred to the payment's address in
         * this transaction.
         *
         * Lets the verifier tell "you sent the wrong token" from "you paid
         * somebody else" — and, just as importantly, keeps a worthless token
         * minted to look like a payment from ever being counted as one.
         */
        public bool $creditedOtherToken = false,
    ) {}

    public function credited(): bool
    {
        return TokenAmount::compare($this->creditedAmount, '0') > 0;
    }
}
