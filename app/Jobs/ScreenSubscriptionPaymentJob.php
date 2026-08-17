<?php

namespace App\Jobs;

use App\Actions\Billing\FinalizeScreenedPayment;
use App\Contracts\Billing\AddressScreener;
use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentScreeningDelayedNotification;
use App\Support\Billing\ScreeningResult;
use App\Support\Billing\ScreeningSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ScreenSubscriptionPaymentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 12;

    public int $timeout = 45;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $paymentId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return $this->paymentId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600, 900, 1800, 1800, 3600, 3600, 3600, 3600];
    }

    public function handle(AddressScreener $screener, FinalizeScreenedPayment $finalize): void
    {
        $payment = SubscriptionPayment::query()->find($this->paymentId);

        if (
            $payment === null
            || $payment->status !== PaymentStatus::Submitted
            || $payment->chain_verified_at === null
        ) {
            return;
        }

        if (
            blank($payment->tx_hash)
            || blank($payment->from_address)
            || blank($payment->pay_to_address)
            || blank($payment->received_amount)
        ) {
            $result = ScreeningResult::unknown('application', 'missing_subject');
        } else {
            try {
                // Network I/O happens before FinalizeScreenedPayment opens its
                // transaction, so a slow provider never holds payment locks.
                $result = $screener->screen(ScreeningSubject::fromPayment($payment));
            } catch (Throwable) {
                $result = ScreeningResult::unknown('application', 'provider_exception');
            }
        }

        $finalize($payment, $result);

        if ($result->risk->isDefinitive()) {
            return;
        }

        $this->release($this->delayForCurrentAttempt());
    }

    public function failed(?Throwable $exception): void
    {
        $payment = SubscriptionPayment::query()->find($this->paymentId);

        if ($payment === null || $payment->status !== PaymentStatus::Submitted) {
            return;
        }

        $adminEmail = trim((string) config('app.admin_email'));
        $admin = $adminEmail === ''
            ? null
            : User::query()->where('email', $adminEmail)->first();

        $alertMinutes = (int) config('billing.review_alert_minutes', 15);

        if ($admin !== null && Cache::add('billing.screening-delayed', true, now()->addMinutes($alertMinutes))) {
            $waiting = SubscriptionPayment::query()
                ->where('status', PaymentStatus::Submitted->value)
                ->whereNotNull('chain_verified_at')
                ->count();

            $admin->notify(new PaymentScreeningDelayedNotification($waiting));
        }
    }

    private function delayForCurrentAttempt(): int
    {
        $schedule = $this->backoff();

        return $schedule[min(max($this->attempts() - 1, 0), count($schedule) - 1)];
    }
}
