<?php

namespace App\Console\Commands;

use App\Actions\Gamification\AwardMilestones;
use App\Enums\Feature;
use App\Enums\Milestone;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('miles:rollout {--execute : Persist launch grants and grandfathered unlocks}')]
#[Description('Audit or execute the idempotent Miles launch backfill')]
class MilesRolloutCommand extends Command
{
    public function handle(AwardMilestones $awardMilestones): int
    {
        $eligibleUsers = User::query()
            ->whereNotNull('email_verified_at')
            ->whereNotNull('birthdate')
            ->count();
        $activeProUsers = User::query()->where('pro_until', '>', now())->count();
        $openPayments = SubscriptionPayment::query()->inFlight()->count();

        $this->table(
            ['Rollout check', 'Count'],
            [
                ['Eligible launch grants', $eligibleUsers],
                ['Active Pro accounts', $activeProUsers],
                ['Open or settling payments', $openPayments],
            ],
        );

        if (! $this->option('execute')) {
            $this->comment('Dry run only. Re-run with --execute after open payments are resolved or expired.');

            return self::SUCCESS;
        }

        if ($openPayments > 0) {
            $this->error('Rollout stopped because payments are still open or settling.');

            return self::FAILURE;
        }

        $grantCount = 0;
        $unlockCount = 0;
        $paidFeatures = collect((array) config('miles.paid_modules'))
            ->map(fn (string $value): Feature => Feature::from($value));

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($awardMilestones, $paidFeatures, &$grantCount, &$unlockCount): void {
            foreach ($users as $user) {
                if ($user->hasVerifiedEmail() && ! $user->requiresProfileCompletion()) {
                    $grantCount += $awardMilestones->award($user, Milestone::VerifiedEmail) !== null ? 1 : 0;
                }

                $features = $user->featureSet();

                foreach ($paidFeatures as $feature) {
                    if (! $features->enabled($feature)) {
                        continue;
                    }

                    $unlock = $user->featureUnlocks()->firstOrCreate(
                        ['feature' => $feature->value],
                        ['unlocked_at' => now()],
                    );
                    $unlockCount += $unlock->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        $this->info("Rollout complete: {$grantCount} launch grants and {$unlockCount} grandfathered unlocks created.");

        if ($activeProUsers > 0) {
            $this->comment('Run miles:convert-pro next. It will choose the individual or batch path from the active-user count.');
        }

        return self::SUCCESS;
    }
}
