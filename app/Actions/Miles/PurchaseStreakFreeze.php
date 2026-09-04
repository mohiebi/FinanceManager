<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\MileWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PurchaseStreakFreeze
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(User $user): MileWallet
    {
        return DB::transaction(function () use ($user): MileWallet {
            MileWallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
            $wallet = MileWallet::query()->where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            if ($wallet->freezes_held >= (int) config('miles.streak_freeze_maximum')) {
                throw ValidationException::withMessages(['freeze' => __('You already hold the maximum number of Streak Freezes.')]);
            }

            ($this->adjustMiles)(
                $user,
                -((int) config('miles.streak_freeze_price')),
                MilesReason::StreakFreeze,
                'freeze-purchase:'.Str::ulid(),
                action: 'streak_freeze',
            );

            $wallet->increment('freezes_held');

            return $wallet->refresh();
        }, 3);
    }
}
