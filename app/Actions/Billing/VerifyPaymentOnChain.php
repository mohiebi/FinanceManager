<?php

namespace App\Actions\Billing;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Exceptions\ExplorerUnavailable;
use App\Models\SubscriptionPayment;
use App\Services\Billing\ChainExplorerFactory;
use App\Support\Billing\PaymentVerification;
use App\Support\Billing\TokenAmount;
use App\Support\Billing\TokenTransfer;

/**
 * Decides whether a claimed transaction actually paid for a subscription.
 *
 * Writes nothing. Every rule below is a pure function of the payment row and
 * what the chain said, which is what makes each of them testable on its own and
 * what keeps the decision identical across chains.
 */
final readonly class VerifyPaymentOnChain
{
    public function __construct(private ChainExplorerFactory $explorers) {}

    public function __invoke(SubscriptionPayment $payment): PaymentVerification
    {
        if ($payment->status !== PaymentStatus::Submitted || $payment->network === null) {
            return PaymentVerification::retry(PaymentFailureReason::TxNotFound);
        }

        try {
            $transfer = $this->explorers->for($payment->network)->transferFor($payment);
        } catch (ExplorerUnavailable) {
            // Our problem, not the buyer's. Stays retryable so a real payment is
            // never burned by an outage.
            return PaymentVerification::retry(PaymentFailureReason::ExplorerUnavailable);
        }

        // Broadcast but not mined. Ordinary, and worth waiting for.
        if ($transfer === null) {
            return PaymentVerification::retry(PaymentFailureReason::TxNotFound);
        }

        if (! $transfer->succeeded) {
            return PaymentVerification::rejected(PaymentFailureReason::Reverted, $transfer);
        }

        if (! $transfer->credited()) {
            return PaymentVerification::rejected($this->uncreditedReason($payment, $transfer), $transfer);
        }

        if ($this->predatesTheIntent($payment, $transfer) || $this->tooOld($transfer)) {
            return PaymentVerification::rejected(PaymentFailureReason::TxTooOld, $transfer);
        }

        if ($payment->quote_expires_at !== null && $transfer->blockTimestamp->greaterThan($payment->quote_expires_at)) {
            // Paid at a price we had stopped honouring. Routed to review rather
            // than refused outright, because the money did arrive.
            return PaymentVerification::rejected(PaymentFailureReason::QuoteExpired, $transfer);
        }

        // Every intent has its own single-use address, so an overpayment cannot
        // be confused with another buyer's intent. Reject only a short payment;
        // the signer receives the amount that actually arrived and settles the
        // complete balance into the appropriate vault.
        if (TokenAmount::compare($transfer->creditedAmount, $payment->expectedBaseUnits()) < 0) {
            return PaymentVerification::rejected(PaymentFailureReason::AmountMismatch, $transfer);
        }

        if ($transfer->confirmations < $payment->network->confirmationsRequired()) {
            return PaymentVerification::awaitingConfirmations($transfer);
        }

        return PaymentVerification::confirmed($transfer);
    }

    /**
     * Why nothing reached us, when the transaction itself succeeded.
     *
     * For a token the answer is simple — no Transfer log named us, so it paid
     * somebody else. For native currency it is not: ether forwarded by a
     * contract leaves no trace a receipt can show, so a transaction that ran
     * contract code and did not name us directly is treated as unreadable
     * rather than wrong, and goes to a human instead of being refused.
     */
    private function uncreditedReason(SubscriptionPayment $payment, TokenTransfer $transfer): PaymentFailureReason
    {
        if ($transfer->creditedOtherToken) {
            return PaymentFailureReason::WrongToken;
        }

        if ($payment->token_contract !== null) {
            return PaymentFailureReason::WrongRecipient;
        }

        return $transfer->touchedContract
            ? PaymentFailureReason::NativeTransferNotVisible
            : PaymentFailureReason::WrongRecipient;
    }

    /**
     * A transaction mined before its intent existed belongs to something else —
     * the rule that stops an old transfer being dug up and claimed.
     */
    private function predatesTheIntent(SubscriptionPayment $payment, TokenTransfer $transfer): bool
    {
        $earliest = $payment->created_at->copy()
            ->subSeconds((int) config('billing.clock_skew_seconds', 120));

        return $transfer->blockTimestamp->lessThan($earliest);
    }

    private function tooOld(TokenTransfer $transfer): bool
    {
        return $transfer->blockTimestamp
            ->lessThan(now()->subHours((int) config('billing.tx_max_age_hours', 72)));
    }
}
