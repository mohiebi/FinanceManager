<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\AdvisorRecommendation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SettleAdvisorMiles
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function __invoke(AdvisorRecommendation $recommendation, string $outcome): AdvisorRecommendation
    {
        if (! in_array($outcome, ['full', 'guidance', 'failure'], true)) {
            throw new InvalidArgumentException('Unknown Advisor settlement outcome.');
        }

        return DB::transaction(function () use ($recommendation, $outcome): AdvisorRecommendation {
            $recommendation = AdvisorRecommendation::query()->lockForUpdate()->findOrFail($recommendation->getKey());
            $target = match ($outcome) {
                'full' => (int) $recommendation->quoted_miles,
                'guidance' => (int) config('miles.advisor.guidance'),
                'failure' => 0,
            };
            $target = config('miles.advisor_charging') ? $target : 0;
            $currentlyCommitted = $recommendation->miles_settled_at === null
                ? (int) $recommendation->reserved_miles
                : (int) $recommendation->charged_miles;
            $refund = max(0, $currentlyCommitted - $target);

            if ($refund > 0) {
                ($this->adjustMiles)(
                    $recommendation->user,
                    $refund,
                    MilesReason::AdvisorRefund,
                    "advisor:{$recommendation->getKey()}:refund:{$outcome}",
                    $recommendation,
                    ['outcome' => $outcome],
                );
            }

            $recommendation->forceFill([
                'charged_miles' => $target,
                'miles_outcome' => $outcome,
                'miles_settled_at' => now(),
            ])->save();

            return $recommendation->refresh();
        }, 3);
    }
}
