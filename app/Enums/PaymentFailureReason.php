<?php

namespace App\Enums;

/**
 * Why a payment did not settle.
 *
 * The split that matters is {@see self::isRetryable()}. Only two cases here
 * describe a world that might change on its own — everything else is a judgement
 * about a transaction that will never look different no matter how often it is
 * re-read.
 *
 * A retryable reason must never leave a payment in {@see PaymentStatus::Failed}:
 * an unreachable node is our problem, and burning somebody's real money over it
 * is the worst outcome this feature can produce.
 */
enum PaymentFailureReason: string
{
    /** Not on the chain yet, or not on it at all. */
    case TxNotFound = 'tx_not_found';

    /** Nothing in the transaction moved value to our address. */
    case WrongRecipient = 'wrong_recipient';

    /** The transfer was some other token than the one the intent was priced in. */
    case WrongToken = 'wrong_token';

    /** Paid on a different chain than the intent named. */
    case WrongChain = 'wrong_chain';

    /** The amount fell outside the band the intent expects. */
    case AmountMismatch = 'amount_mismatch';

    /** Some other payment already claimed this transaction. */
    case AlreadyClaimed = 'already_claimed';

    /** Mined before the intent existed, or too long ago to belong to it. */
    case TxTooOld = 'tx_too_old';

    /** Paid after the quoted rate stopped being honoured. */
    case QuoteExpired = 'quote_expired';

    /** The transaction itself failed on-chain. */
    case Reverted = 'reverted';

    /**
     * Native currency moved inside a contract call, which a receipt does not
     * record. Real money, genuinely paid, that plain JSON-RPC cannot see.
     */
    case NativeTransferNotVisible = 'native_transfer_not_visible';

    /** We could not reach the chain. Never the buyer's fault. */
    case ExplorerUnavailable = 'explorer_unavailable';

    /** An administrator refused it. */
    case AdminRejected = 'admin_rejected';

    /** The intent's window closed unpaid. */
    case Expired = 'expired';

    public function label(): string
    {
        $translationKey = "billing.failures.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::TxNotFound => 'We could not find that transaction.',
            self::WrongRecipient => 'That transaction did not pay our address.',
            self::WrongToken => 'That transaction moved a different token.',
            self::WrongChain => 'That transaction is on a different chain.',
            self::AmountMismatch => 'The amount does not match what this payment expects.',
            self::AlreadyClaimed => 'That transaction has already been used for another payment.',
            self::TxTooOld => 'That transaction is older than this payment.',
            self::QuoteExpired => 'The quoted amount had expired by the time this was paid.',
            self::Reverted => 'That transaction failed on-chain.',
            self::NativeTransferNotVisible => 'We could not read that transfer automatically.',
            self::ExplorerUnavailable => 'We could not reach the network. We will keep trying.',
            self::AdminRejected => 'This payment was rejected after review.',
            self::Expired => 'This payment window closed before it was paid.',
        };
    }

    /** Whether checking again later could plausibly reach a different answer. */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::TxNotFound, self::ExplorerUnavailable => true,
            default => false,
        };
    }

    /**
     * Whether a human should look at this rather than the buyer being told no.
     *
     * These are the cases where money almost certainly did arrive but the
     * automatic checks cannot honour it on their own.
     */
    public function needsReview(): bool
    {
        return match ($this) {
            self::ExplorerUnavailable,
            self::QuoteExpired,
            self::AmountMismatch,
            self::WrongChain,
            self::NativeTransferNotVisible => true,
            default => false,
        };
    }
}
