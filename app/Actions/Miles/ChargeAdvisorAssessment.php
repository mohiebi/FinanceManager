<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\InvestorAssessment;
use App\Models\User;

final readonly class ChargeAdvisorAssessment
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function quote(User $user, ?InvestorAssessment $assessment = null): int
    {
        $usedFreeCompletion = $user->investorAssessments()
            ->when($assessment instanceof InvestorAssessment, fn ($query) => $query->where('id', '!=', $assessment->getKey()))
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays((int) config('miles.advisor.assessment_free_days')))
            ->exists();

        return $usedFreeCompletion ? (int) config('miles.advisor.assessment') : 0;
    }

    /** @return array{quoted: int, charged: int} */
    public function __invoke(InvestorAssessment $assessment): array
    {
        $quoted = $this->quote($assessment->user, $assessment);
        $charged = config('miles.advisor_charging') ? $quoted : 0;

        if ($charged > 0) {
            ($this->adjustMiles)(
                $assessment->user,
                -$charged,
                MilesReason::AdvisorReservation,
                "advisor-assessment:{$assessment->getKey()}:complete",
                $assessment,
                ['service' => 'assessment'],
                action: 'advisor_assessment',
            );
        }

        return ['quoted' => $quoted, 'charged' => $charged];
    }
}
