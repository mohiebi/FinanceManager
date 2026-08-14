<?php

namespace App\Jobs;

use App\Actions\Billing\SettleCouponRedemption;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The billing housekeeping sweep.
 *
 * Note what is absent: there is no downgrade pass. `pro_until` is compared
 * against the clock every time it is read, so an entitlement lapses on its own
 * the instant it expires — no job has to go and take it away, and none can
 * forget to. What is left here is tidying intents nobody paid, restarting
 * verifications a restarted worker dropped, and the two reminders that matter
 * precisely because nothing renews itself.
 *
 * Every pass is idempotent, so running twice changes nothing.
 */
class ReconcileSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * A verification untouched for this long has been dropped rather than
     * delayed — the backoff schedule never leaves a gap this wide early on.
     */
    private const STALLED_AFTER_MINUTES = 30;

    public function __construct()
    {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        $this->expireStaleIntents();
        $this->restartStalledVerifications();
        $this->warnAboutExpiry();
        $this->announceExpiry();
    }

    /**
     * Close intents whose window ran out unpaid.
     */
    private function expireStaleIntents(): void
    {
        $stale = SubscriptionPayment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('expires_at', '<=', now());

        // Collected before the update, because afterwards nothing identifies
        // which rows this run expired.
        $expiredIds = $stale->clone()->pluck('id')->all();

        $stale->update([
            'status' => PaymentStatus::Expired->value,
            'failure_reason' => PaymentFailureReason::Expired->value,
            'updated_at' => now(),
        ]);

        // Any coupon those intents were holding goes back into the pool.
        // Without this a single-use code would be spent by the first person who
        // opened an intent and wandered off, not by the first who paid.
        app(SettleCouponRedemption::class)->releaseMany($expiredIds);
    }

    /**
     * Re-queue verifications that stopped moving.
     *
     * A worker restart or a queue:restart drops in-flight jobs on the floor;
     * without this, a buyer's payment would sit in "checking" forever with their
     * money already spent.
     */
    private function restartStalledVerifications(): void
    {
        SubscriptionPayment::query()
            ->settling()
            ->where('updated_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->where('created_at', '>=', now()->subHours((int) config('billing.tx_max_age_hours', 72) + 24))
            ->pluck('id')
            ->each(fn (string $id) => VerifySubscriptionPaymentJob::dispatch($id));
    }

    /**
     * Remind buyers before their access runs out.
     *
     * The marker stores the expiry it refers to rather than a timestamp, so a
     * renewal re-arms this by itself: the new `pro_until` no longer matches what
     * was warned about, and the next expiry gets its own reminder with no reset
     * step anywhere.
     */
    private function warnAboutExpiry(): void
    {
        $days = (int) config('billing.expiry_warning_days', 7);

        User::query()
            ->whereNotNull('pro_until')
            ->where('pro_until', '>', now())
            ->where('pro_until', '<=', now()->addDays($days))
            ->where(fn ($query) => $query
                ->whereNull('pro_expiry_warned_for')
                ->orWhereColumn('pro_expiry_warned_for', '!=', 'pro_until'))
            ->each(function (User $user): void {
                $user->notify(new SubscriptionExpiringNotification($user->pro_until));

                $user->forceFill(['pro_expiry_warned_for' => $user->pro_until])->save();
            });
    }

    private function announceExpiry(): void
    {
        User::query()
            ->whereNotNull('pro_until')
            ->where('pro_until', '<=', now())
            // Bounded so that switching this on does not mail everybody who ever
            // lapsed, and so a long-dormant account is left in peace.
            ->where('pro_until', '>', now()->subDays(30))
            ->where(fn ($query) => $query
                ->whereNull('pro_expired_notified_for')
                ->orWhereColumn('pro_expired_notified_for', '!=', 'pro_until'))
            ->each(function (User $user): void {
                $user->notify(new SubscriptionExpiredNotification($user->pro_until));

                $user->forceFill(['pro_expired_notified_for' => $user->pro_until])->save();
            });
    }
}
