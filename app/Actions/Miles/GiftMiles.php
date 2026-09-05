<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Enums\ReferralStage;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class GiftMiles
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(User $sender, User $recipient, int $amount): void
    {
        if (! config('miles.gifting_enabled') || ! in_array($amount, config('miles.referrals.gift_amounts'), true)) {
            throw ValidationException::withMessages(['amount' => __('Choose an available gift amount.')]);
        }

        $referral = Referral::query()
            ->where('referrer_id', $sender->getKey())
            ->where('referred_user_id', $recipient->getKey())
            ->whereHas('rewards', fn ($query) => $query->where('stage', ReferralStage::Activated))
            ->first();

        if (! $referral instanceof Referral) {
            throw ValidationException::withMessages(['recipient_id' => __('Miles can only be gifted to your activated referrals.')]);
        }

        DB::transaction(function () use ($sender, $recipient, $amount, $referral): void {
            $monthTotal = $this->giftedSince($sender, $sender->localToday()->startOfMonth());
            $friendTotal = abs((int) $sender->mileLedgerEntries()
                ->where('reason', MilesReason::ReferralGiftSent)
                ->where('source_type', $referral->getMorphClass())
                ->where('source_id', $referral->getKey())
                ->sum('amount'));
            $rollingTotal = $this->giftedSince($sender, now()->subDays(365))
                + (int) $sender->mileLedgerEntries()
                    ->where('reason', MilesReason::Referral)
                    ->where('created_at', '>=', now()->subDays(365))
                    ->sum('amount');

            if ($monthTotal + $amount > (int) config('miles.referrals.gift_monthly_cap')) {
                throw ValidationException::withMessages(['amount' => __('This gift exceeds your monthly gifting limit.')]);
            }
            if ($friendTotal + $amount > (int) config('miles.referrals.gift_per_friend_cap')) {
                throw ValidationException::withMessages(['amount' => __('This gift exceeds the limit for this friend.')]);
            }
            if ($rollingTotal + $amount > (int) config('miles.referrals.rolling_cap')) {
                throw ValidationException::withMessages(['amount' => __('This gift exceeds your rolling referral limit.')]);
            }

            $transferId = (string) Str::ulid();
            ($this->adjustMiles)(
                $sender,
                -$amount,
                MilesReason::ReferralGiftSent,
                "gift:{$transferId}:sent",
                $referral,
                transferId: $transferId,
                action: 'referral_gift',
            );
            ($this->adjustMiles)(
                $recipient,
                $amount,
                MilesReason::ReferralGiftReceived,
                "gift:{$transferId}:received",
                $referral,
                transferId: $transferId,
            );
        }, 3);
    }

    private function giftedSince(User $user, mixed $date): int
    {
        return abs((int) $user->mileLedgerEntries()
            ->where('reason', MilesReason::ReferralGiftSent)
            ->where('created_at', '>=', $date)
            ->sum('amount'));
    }
}
