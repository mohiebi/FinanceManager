<?php

namespace App\Actions\Billing;

use App\Enums\DepositAddressStatus;
use App\Enums\PaymentStatus;
use App\Enums\RiskCaseStatus;
use App\Models\PaymentRiskCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AuthorizeRiskSettlement
{
    public function __construct(private QueuePaymentSettlement $queuePaymentSettlement) {}

    public function __invoke(PaymentRiskCase $case, User $admin, string $note): void
    {
        DB::transaction(function () use ($case, $admin, $note): void {
            $locked = PaymentRiskCase::query()->with(['payment.depositAddress'])
                ->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === RiskCaseStatus::Pending, 409);
            abort_if($locked->review_expires_at->isPast(), 409);
            abort_unless($locked->payment->status === PaymentStatus::RiskReview, 409);

            $locked->forceFill([
                'status' => RiskCaseStatus::Authorized,
                'authorized_by_admin_id' => $admin->getKey(),
                'authorized_at' => now(),
                'authorization_note' => $note,
            ])->save();
            $locked->payment->depositAddress?->forceFill(['status' => DepositAddressStatus::Settling])->save();
            ($this->queuePaymentSettlement)($locked->payment, true);
        });
    }
}
