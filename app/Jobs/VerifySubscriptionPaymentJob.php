<?php

namespace App\Jobs;

use App\Actions\Billing\CreditPurchasedMiles;
use App\Actions\Billing\GrantProAccess;
use App\Actions\Billing\SettleCouponRedemption;
use App\Actions\Billing\VerifyPaymentOnChain;
use App\Enums\GrantReason;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentNeedsReviewNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Notifications\SubscriptionPaymentFailedNotification;
use App\Support\Billing\PaymentVerification;
use App\Support\Billing\TokenAmount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Settles one claimed payment against the chain.
 *
 * The rule this job exists to protect: only a verdict that cannot change is
 * allowed to fail a payment. An unreachable node, a rate-limited endpoint or a
 * transaction that has not been mined yet all leave the payment exactly where
 * it was, because somebody's real money is on the other side of each of them.
 */
class VerifySubscriptionPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Enough attempts to cover a long outage — the backoff below stretches the
     * last of them across several hours.
     */
    public int $tries = 12;

    public int $timeout = 45;

    public int $uniqueFor = 3600;

    /**
     * Takes an id rather than the model on purpose: a deleted row becomes an
     * early return instead of a deserialization failure in the logs.
     */
    public function __construct(public readonly string $paymentId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return $this->paymentId;
    }

    /**
     * Tight at first, because twelve confirmations on a twelve-second chain is
     * about two and a half minutes; long at the end, because by then the only
     * thing still failing is a node that is properly down.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300, 600, 900, 1800, 1800, 3600, 3600, 3600];
    }

    public function handle(VerifyPaymentOnChain $verify, GrantProAccess $grant): void
    {
        $payment = SubscriptionPayment::query()->find($this->paymentId);

        // Already settled, withdrawn or gone. Re-running is a no-op by design:
        // the job is retried freely and must never grant twice.
        if ($payment === null || $payment->status !== PaymentStatus::Submitted) {
            return;
        }

        $verification = $verify($payment);

        if ($verification->confirmed) {
            $this->settle($payment, $verification, $grant);

            return;
        }

        $this->recordProgress($payment, $verification);

        if ($verification->retryable) {
            $this->release($this->delayForCurrentAttempt());

            return;
        }

        $payment->forceFill(['status' => PaymentStatus::Failed])->save();

        // Who hears about this depends on whether the answer is actually final.
        // A reviewable reason means money probably did arrive and an admin may
        // well approve it within the hour — telling the buyer their payment
        // failed first, only to reverse it, is worse than telling them nothing.
        if ($verification->reason?->needsReview() === true) {
            $this->askForReview($payment->fresh());

            return;
        }

        $payment->user->notify(new SubscriptionPaymentFailedNotification($payment->fresh()));
    }

    /**
     * Out of attempts.
     *
     * Deliberately leaves the payment in Submitted rather than failing it. By
     * definition we only get here on retryable verdicts, which means we never
     * managed to read the chain — and a payment that could not be checked is
     * not a payment that did not happen. A human picks it up from the admin
     * queue instead.
     */
    public function failed(?Throwable $exception): void
    {
        $payment = SubscriptionPayment::query()->find($this->paymentId);

        if ($payment === null || $payment->status !== PaymentStatus::Submitted) {
            return;
        }

        $payment->forceFill([
            'failure_reason' => PaymentFailureReason::ExplorerUnavailable,
        ])->save();

        $this->askForReview($payment->fresh());
    }

    /**
     * Ask the operator to decide, once per burst.
     *
     * Debounced per reason, because the thing that strands payments in bulk is
     * a chain being unreachable — which parks every payment in flight at the
     * same moment. One message saying how many are waiting is far more useful
     * than twenty saying the same thing, and the console shows the rest.
     *
     * A distinct kind of problem still gets its own alert, so a single amount
     * mismatch is never buried by an ongoing outage.
     */
    private function askForReview(SubscriptionPayment $payment): void
    {
        $adminEmail = trim((string) config('app.admin_email'));

        if ($adminEmail === '') {
            return;
        }

        $admin = User::query()->where('email', $adminEmail)->first();

        if ($admin === null) {
            return;
        }

        $window = (int) config('billing.review_alert_minutes', 15);
        $key = 'billing.review-alert.'.($payment->failure_reason?->value ?? 'unknown');

        // add() only succeeds when the key is absent, so the first payment of a
        // burst sends and the rest are absorbed.
        if (! Cache::add($key, true, now()->addMinutes($window))) {
            return;
        }

        $admin->notify(new PaymentNeedsReviewNotification($payment, $this->waitingForReview()));
    }

    private function waitingForReview(): int
    {
        return SubscriptionPayment::query()
            ->whereIn('failure_reason', array_column(PaymentFailureReason::needingReview(), 'value'))
            ->whereNot('status', PaymentStatus::Confirmed->value)
            ->count();
    }

    private function settle(
        SubscriptionPayment $payment,
        PaymentVerification $verification,
        GrantProAccess $grant,
    ): void {
        DB::transaction(function () use ($payment, $verification, $grant): void {
            // Re-read under a lock: two workers reaching this at once must not
            // both grant. The status check inside the lock is what makes that safe.
            $locked = SubscriptionPayment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PaymentStatus::Submitted) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentStatus::Confirmed,
                'received_amount' => $verification->receivedAmount === null
                    ? null
                    : TokenAmount::toDecimal($verification->receivedAmount, (int) $locked->asset_decimals),
                'confirmations' => $verification->confirmations,
                'block_number' => $verification->blockNumber,
                'block_timestamp' => $verification->blockTimestamp,
                'from_address' => $verification->fromAddress,
                'verified_at' => now(),
                'failure_reason' => null,
            ])->save();

            // Same transaction as the status write, so there is no instant where
            // a payment reads as paid without the months behind it.
            if ($locked->miles_pack !== null) {
                app(CreditPurchasedMiles::class)($locked);
                app(SettleCouponRedemption::class)->consume($locked);

                return;
            }
            $subscriptionGrant = $grant($locked->user, (int) $locked->months, GrantReason::Payment, $locked->getKey());

            // Inside the same locked transaction as the status write, so a
            // coupon claim can never be spent by a payment that did not settle.
            app(SettleCouponRedemption::class)->consume($locked, $subscriptionGrant);

            // After commit, deliberately: a mail provider having a bad minute
            // must never be able to roll back somebody's entitlement.
            DB::afterCommit(fn () => $locked->user->notify(
                new SubscriptionActivatedNotification($locked, $subscriptionGrant->pro_until_after)
            ));
        });
    }

    private function recordProgress(SubscriptionPayment $payment, PaymentVerification $verification): void
    {
        $payment->forceFill([
            'attempts' => (int) $payment->attempts + 1,
            'confirmations' => $verification->confirmations ?? $payment->confirmations,
            'block_number' => $verification->blockNumber ?? $payment->block_number,
            'block_timestamp' => $verification->blockTimestamp ?? $payment->block_timestamp,
            'from_address' => $verification->fromAddress ?? $payment->from_address,
            'received_amount' => $verification->receivedAmount === null
                ? $payment->received_amount
                : TokenAmount::toDecimal($verification->receivedAmount, (int) $payment->asset_decimals),
            // Set even while retryable, so the buyer can be told we are having
            // trouble reaching the chain rather than left watching a spinner.
            'failure_reason' => $verification->reason,
        ])->save();
    }

    private function delayForCurrentAttempt(): int
    {
        $schedule = $this->backoff();

        return $schedule[min(max($this->attempts() - 1, 0), count($schedule) - 1)];
    }
}
