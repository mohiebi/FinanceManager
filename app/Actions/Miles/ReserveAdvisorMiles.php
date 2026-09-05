<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\AdvisorRecommendation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ReserveAdvisorMiles
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function quote(User $user): int
    {
        $hasFullRun = AdvisorRecommendation::query()
            ->where('user_id', $user->getKey())
            ->where('miles_outcome', 'full')
            ->exists();

        return (int) config('miles.advisor.'.($hasFullRun ? 'recommendation' : 'first_recommendation'));
    }

    public function __invoke(AdvisorRecommendation $recommendation): AdvisorRecommendation
    {
        return DB::transaction(function () use ($recommendation): AdvisorRecommendation {
            $recommendation = AdvisorRecommendation::query()->lockForUpdate()->findOrFail($recommendation->getKey());
            $price = $this->quote($recommendation->user);
            $reserved = 0;

            if (config('miles.advisor_charging')) {
                ($this->adjustMiles)(
                    $recommendation->user,
                    -$price,
                    MilesReason::AdvisorReservation,
                    "advisor:{$recommendation->getKey()}:reserve",
                    $recommendation,
                    ['service' => 'recommendation'],
                    action: 'advisor_recommendation',
                );
                $reserved = $price;
            }

            $recommendation->forceFill([
                'quoted_miles' => $price,
                'reserved_miles' => $reserved,
            ])->save();

            return $recommendation->refresh();
        }, 3);
    }
}
