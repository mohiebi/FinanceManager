<?php

namespace App\Jobs;

use App\Actions\Billing\SettleCouponRedemption;
use App\Enums\DepositAddressStatus;
use App\Enums\PaymentStatus;
use App\Enums\RiskCaseStatus;
use App\Models\PaymentRiskCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class ExpirePaymentRiskCasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SettleCouponRedemption $coupons): void
    {
        PaymentRiskCase::query()
            ->with(['payment.depositAddress'])
            ->where('status', RiskCaseStatus::Pending->value)
            ->where('review_expires_at', '<=', now())
            ->chunkById(100, function ($cases) use ($coupons): void {
                foreach ($cases as $case) {
                    DB::transaction(function () use ($case, $coupons): void {
                        $locked = PaymentRiskCase::query()->with(['payment.depositAddress'])
                            ->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

                        if ($locked->status !== RiskCaseStatus::Pending || $locked->review_expires_at->isFuture()) {
                            return;
                        }

                        $locked->forceFill(['status' => RiskCaseStatus::Expired])->save();
                        $locked->payment->forceFill(['status' => PaymentStatus::Failed])->save();
                        $locked->payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Retired])->save();
                        $coupons->release($locked->payment);
                    });
                }
            });
    }
}
