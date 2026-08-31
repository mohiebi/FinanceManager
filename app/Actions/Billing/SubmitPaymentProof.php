<?php

namespace App\Actions\Billing;

use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Jobs\VerifySubscriptionPaymentJob;
use App\Models\SubscriptionPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records the transaction a buyer says paid for their subscription.
 *
 * The write itself is the claim. There is no "is this hash already taken?"
 * lookup beforehand, on purpose: check-then-write leaves a window for two
 * buyers to both pass the check, and this is precisely the primitive meant to
 * stop one person claiming another's transaction. The unique index resolves the
 * race inside the database, where it cannot interleave.
 */
final readonly class SubmitPaymentProof
{
    public function __invoke(SubscriptionPayment $payment, string $txHash): PaymentProofResult
    {
        try {
            $result = DB::transaction(function () use ($payment, $txHash): PaymentProofResult {
                $locked = SubscriptionPayment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->network === null) {
                    return PaymentProofResult::rejected(PaymentFailureReason::WrongChain);
                }

                if (! $locked->isOpen()) {
                    return PaymentProofResult::rejected(PaymentFailureReason::Expired);
                }

                if (! $locked->quoteIsLive()) {
                    return PaymentProofResult::rejected(PaymentFailureReason::QuoteExpired);
                }

                $hash = $locked->network->normalizeTxHash($txHash);

                // The form request checks this too. Repeated here because
                // everything downstream assumes a hash of exactly this shape.
                if (preg_match($locked->network->txHashPattern(), $hash) !== 1) {
                    return PaymentProofResult::rejected(PaymentFailureReason::TxNotFound);
                }

                $locked->forceFill([
                    'status' => PaymentStatus::Submitted,
                    'tx_hash' => $hash,
                    'submitted_at' => now(),
                    'attempts' => 0,
                    'failure_reason' => null,
                ])->save();

                return PaymentProofResult::accepted();
            });
        } catch (UniqueConstraintViolationException) {
            return PaymentProofResult::rejected(PaymentFailureReason::AlreadyClaimed);
        }

        if (! $result->accepted) {
            return $result;
        }

        // Delayed a little: a hash pasted the instant a wallet returns it is
        // often still propagating, and starting with a miss wastes the tightest
        // retry in the backoff schedule.
        VerifySubscriptionPaymentJob::dispatch($payment->getKey())
            ->delay(now()->addSeconds(15));

        return $result;
    }
}
