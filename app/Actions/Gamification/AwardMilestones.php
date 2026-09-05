<?php

namespace App\Actions\Gamification;

use App\Actions\Miles\AdjustMiles;
use App\Enums\Feature;
use App\Enums\MilesReason;
use App\Enums\Milestone;
use App\Models\User;
use App\Models\UserMilestone;
use App\Notifications\MilestoneNotification;
use App\Support\StreakSummary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Marks the moments a user reaches, exactly once each.
 *
 * Fire-once is enforced by the table's unique key rather than by checking first,
 * so two concurrent writes cannot both decide they were the hundredth.
 */
class AwardMilestones
{
    public function __construct(private readonly AdjustMiles $adjustMiles) {}

    /**
     * Users with no count-based milestone left to earn, for this request.
     *
     * The importer writes one row at a time, so without this the whole
     * evaluation would repeat for every line of a CSV.
     *
     * @var array<int, true>
     */
    private array $settled = [];

    /** @var array<int, User> */
    private array $users = [];

    /**
     * Evaluate the milestones that depend on how many records exist.
     *
     * Takes an id rather than a model so the common case — a user who has
     * already earned everything count-based — costs nothing at all, and so the
     * observer never has to lazy-load a User the caller already holds.
     *
     * Reads counts and nothing else, never an amount, which is both the point of
     * the mechanic and what keeps it working with the vault armed.
     */
    public function afterTransaction(int $userId): void
    {
        if (isset($this->settled[$userId])) {
            return;
        }

        $user = $this->userFor($userId);

        if ($user === null || ! $user->hasFeature(Feature::Gamification)) {
            $this->settled[$userId] = true;

            return;
        }

        $earned = $user->milestones()->pluck('key')->all();

        $remaining = array_filter(
            Milestone::countBased(),
            fn (Milestone $milestone): bool => ! in_array($milestone->value, $earned, true),
        );

        if ($remaining === []) {
            $this->settled[$userId] = true;

            return;
        }

        foreach ($remaining as $milestone) {
            if ($this->hasAtLeast($user, $milestone->transactionThreshold())) {
                $this->award($user, $milestone);
            }
        }
    }

    /**
     * Evaluate the milestones that depend on the shape of the record rather than
     * its size, when the dashboard has already computed both.
     *
     * Awarded on the next visit rather than the instant they become true — there
     * is no write to hook, and a moment surfaced when the user is looking is the
     * one that lands anyway.
     */
    public function afterDashboard(User $user, StreakSummary $streak, bool $lastMonthComplete): void
    {
        if ($streak->currentRun >= 30) {
            $this->award($user, Milestone::ThirtyDayRun);
        }

        // The month just gone, not the one in progress: a current month is only
        // ever "complete" on its final day, so checking it would make this
        // reachable on one day in thirty and decorative on the rest.
        if ($lastMonthComplete) {
            $this->award($user, Milestone::FirstFullMonth);
        }
    }

    /**
     * Award a milestone, doing nothing if the user already has it.
     */
    public function award(User $user, Milestone $milestone): ?UserMilestone
    {
        try {
            $awarded = DB::transaction(function () use ($user, $milestone): UserMilestone {
                $milestoneRecord = $user->milestones()->create([
                    'key' => $milestone->value,
                    'achieved_at' => now(),
                ]);

                ($this->adjustMiles)(
                    $user,
                    $milestone->miles(),
                    $milestone === Milestone::VerifiedEmail ? MilesReason::Welcome : MilesReason::Milestone,
                    'milestone:'.$milestone->value,
                    $milestoneRecord,
                    ['milestone' => $milestone->value],
                );

                return $milestoneRecord;
            });
        } catch (UniqueConstraintViolationException) {
            // Already earned. The race is the normal case for the first
            // milestone, where two rows can land in the same request.
            return null;
        }

        $user->notify(new MilestoneNotification($milestone));

        return $awarded;
    }

    /**
     * Whether the user has at least this many transactions.
     *
     * Bounded on purpose: `count()` scans every row the user owns and would be
     * re-run for each line of an import, growing as it goes. Skipping to the
     * threshold row and asking whether it exists stops at the first N.
     */
    private function hasAtLeast(User $user, int $threshold): bool
    {
        return $user->transactions()
            ->offset($threshold - 1)
            ->limit(1)
            ->exists();
    }

    private function userFor(int $userId): ?User
    {
        return $this->users[$userId] ??= User::query()->find($userId);
    }
}
