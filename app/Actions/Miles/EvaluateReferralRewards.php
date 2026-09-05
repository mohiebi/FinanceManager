<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Models\MileLedgerEntry;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class EvaluateReferralRewards
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(User $user): void
    {
        $referral = $user->referredBy()->first();

        if (! $referral instanceof Referral || in_array($referral->status, ['review', 'rejected'], true)) {
            return;
        }

        if (! $user->hasVerifiedEmail() || $user->requiresProfileCompletion() || ! $this->hasFinancialRecord($user)) {
            return;
        }

        foreach (ReferralStage::cases() as $stage) {
            $this->awardStageWhenEligible($referral, $stage);
        }
    }

    private function awardStageWhenEligible(Referral $referral, ReferralStage $stage): void
    {
        DB::transaction(function () use ($referral, $stage): void {
            $referral = Referral::query()->lockForUpdate()->findOrFail($referral->getKey());

            if ($referral->rewards()->where('stage', $stage)->exists()) {
                return;
            }

            /** @var array{days: int, within_days: int, referrer: int, friend: int} $rule */
            $rule = config("miles.referrals.stages.{$stage->value}");
            $deadline = $referral->referredUser->created_at->copy()->addDays($rule['within_days']);
            $activityDays = $referral->referredUser->mileDays()
                ->where('activity_miles', '>', 0)
                ->whereDate('local_date', '<=', $deadline->toDateString())
                ->count();

            if ($activityDays < $rule['days']) {
                return;
            }

            $referrerEntry = null;
            $issued = (int) MileLedgerEntry::query()
                ->where('user_id', $referral->referrer_id)
                ->where('reason', MilesReason::Referral)
                ->where('created_at', '>=', now()->subDays(365))
                ->sum('amount');
            $gifted = abs((int) MileLedgerEntry::query()
                ->where('user_id', $referral->referrer_id)
                ->where('reason', MilesReason::ReferralGiftSent)
                ->where('created_at', '>=', now()->subDays(365))
                ->sum('amount'));

            if ($issued + $gifted + $rule['referrer'] <= (int) config('miles.referrals.rolling_cap')) {
                $referrerEntry = ($this->adjustMiles)(
                    $referral->referrer,
                    $rule['referrer'],
                    MilesReason::Referral,
                    "referral:{$referral->getKey()}:{$stage->value}:referrer",
                    $referral,
                    ['stage' => $stage->value],
                );
            }

            $friendEntry = ($this->adjustMiles)(
                $referral->referredUser,
                $rule['friend'],
                MilesReason::Referral,
                "referral:{$referral->getKey()}:{$stage->value}:friend",
                $referral,
                ['stage' => $stage->value],
            );

            ReferralReward::query()->create([
                'referral_id' => $referral->getKey(),
                'stage' => $stage,
                'referrer_ledger_entry_id' => $referrerEntry?->getKey(),
                'friend_ledger_entry_id' => $friendEntry->getKey(),
                'awarded_at' => now(),
            ]);

            if ($stage === ReferralStage::Activated) {
                $referral->forceFill(['status' => 'active'])->save();
            }
        }, 3);
    }

    private function hasFinancialRecord(User $user): bool
    {
        return $user->transactions()->exists() || $user->noSpendDays()->exists();
    }
}
