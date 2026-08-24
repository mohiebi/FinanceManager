<?php

namespace App\Jobs;

use App\Enums\SettlementStatus;
use App\Models\PaymentSettlement;
use App\Models\User;
use App\Notifications\SignerLockedNotification;
use App\Services\Billing\WalletSignerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The settlement half of the housekeeping sweep.
 *
 * {@see ReconcileSubscriptionsJob} restarts verifications and screenings a
 * restarted worker dropped, and settlement had no equivalent: once
 * {@see ProcessPaymentSettlementJob} exhausted its attempts the row simply
 * stopped moving, with a buyer's money still sitting at a deposit address.
 *
 * The failure that makes this load-bearing is the ordinary one. The signer
 * boots locked by design, so any restart fails every settlement in flight at the
 * same moment — and a settlement that stopped because nobody typed a passphrase
 * has to start again once somebody has.
 */
final class RequeueStalledSettlementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * The job releases itself every 30 to 60 seconds while it is working, so
     * nothing healthy is ever this quiet.
     */
    private const STALLED_AFTER_MINUTES = 15;

    public function __construct()
    {
        $this->onQueue('billing');
    }

    public function handle(WalletSignerClient $signer): void
    {
        $this->requeueStalled();
        $this->warnWhenSignerIsLocked($signer);
    }

    private function requeueStalled(): void
    {
        // NeedsReview is left alone on purpose: it means the signer found
        // something it will not decide by itself, and re-driving it would only
        // produce the same answer while burying the alert.
        PaymentSettlement::query()
            ->whereIn('status', [
                SettlementStatus::Queued->value,
                SettlementStatus::Submitted->value,
                SettlementStatus::Processing->value,
                SettlementStatus::RetryableFailure->value,
            ])
            ->where('updated_at', '<=', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            ->pluck('id')
            ->each(fn (string $id) => ProcessPaymentSettlementJob::dispatch($id));
    }

    private function warnWhenSignerIsLocked(WalletSignerClient $signer): void
    {
        try {
            $health = $signer->health();
        } catch (Throwable) {
            // Unreachable is a different problem, and the settlement job already
            // raises it against the specific operations it stops.
            return;
        }

        if (($health['locked'] ?? true) !== true) {
            return;
        }

        $waiting = PaymentSettlement::query()
            ->whereNotIn('status', [SettlementStatus::Completed->value, SettlementStatus::Failed->value])
            ->count();

        $adminEmail = trim((string) config('app.admin_email'));

        if ($adminEmail === '' || ! Cache::add('billing.signer-locked', true, now()->addHour())) {
            return;
        }

        User::query()->where('email', $adminEmail)->first()?->notify(new SignerLockedNotification($waiting));
    }
}
