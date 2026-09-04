<?php

namespace App\Actions\Miles;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\Milestone;
use App\Models\User;

final readonly class EnsureWelcomeMiles
{
    public function __construct(private AwardMilestones $awardMilestones) {}

    public function __invoke(User $user): void
    {
        if (! config('miles.enabled') || ! $user->hasVerifiedEmail() || $user->requiresProfileCompletion()) {
            return;
        }

        $this->awardMilestones->award($user, Milestone::VerifiedEmail);
    }
}
