<?php

namespace App\Actions\Goals;

use App\Models\Investment;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\DateFormatter;
use App\Support\Encryption\EncryptedValue;
use App\Support\FrontendLocalization;
use App\Support\GoalPace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Progress toward asset-denominated savings goals.
 *
 * A sibling of BuildPortfolioBreakdown rather than a method on it: goals need no
 * prices, no currency conversion and no P&L, and progress deliberately never
 * routes through `current_value` — a goal in grams is meaningful during a
 * price-feed outage and for a manually-priced custom asset, and tying it to a
 * value would break both.
 *
 * Dates are resolved here for armed and unarmed users alike, because they are
 * never encrypted. Only the quantity maths is mirrored in the browser — see
 * {@see GoalPace} and resources/js/lib/goals.ts.
 */
class BuildGoalProgress
{
    /**
     * @return Collection<int, SavingsGoal>
     */
    public function goalsFor(User $user): Collection
    {
        return $user->savingsGoals()
            ->active()
            ->with('asset')
            ->orderBy('target_date')
            ->get()
            ->filter(fn (SavingsGoal $goal): bool => $goal->asset !== null)
            ->values();
    }

    /**
     * The plaintext path: everything resolved server-side.
     *
     * @param  Collection<int, Investment>  $entries
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user, Collection $entries, ?CarbonImmutable $today = null): array
    {
        $today = $today ?? $user->localToday();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        // Second safety net, mirroring BuildPortfolioBreakdown::handle(): if a
        // caller forgets the vault branch, drop the row rather than treat
        // ciphertext as a number and report a confidently wrong target.
        $holdings = $this->holdingsByAsset($entries);

        return $this->goalsFor($user)
            ->reject(fn (SavingsGoal $goal): bool => $goal->target_quantity instanceof EncryptedValue)
            ->map(function (SavingsGoal $goal) use ($holdings, $today, $calendar): array {
                $window = $this->window($goal, $today);
                // Summed once: two call sites over the same collection is both
                // wasted work and a chance for the pace and the displayed figure
                // to drift apart.
                $current = $this->currentFor($goal, $holdings);

                $pace = GoalPace::compute(
                    baseline: $this->baselineFor($goal, $holdings),
                    current: $current,
                    target: (float) $goal->target_quantity,
                    elapsed: $window['elapsed'],
                    total: $window['total'],
                );

                return [
                    ...$this->presentation($goal, $calendar, $window),
                    ...$pace,
                    'current_quantity' => round($current, 8),
                    'target_quantity' => round((float) $goal->target_quantity, 8),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The armed path: sealed values plus every date already resolved.
     *
     * A sibling payload rather than an extension of the portfolio's, so goals
     * stay independently deferrable and the portfolio's asserted key list does
     * not widen.
     *
     * @return array{goals: array<int, array<string, mixed>>, today: string}
     */
    public function clientPayload(User $user, ?CarbonImmutable $today = null): array
    {
        $today = $today ?? $user->localToday();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        return [
            'goals' => $this->goalsFor($user)
                ->map(function (SavingsGoal $goal) use ($today, $calendar): array {
                    $window = $this->window($goal, $today);

                    return [
                        ...$this->presentation($goal, $calendar, $window),
                        // Ciphertext; the browser decrypts and runs GoalPace's port.
                        'target_quantity' => $goal->target_quantity,
                        'elapsed' => $window['elapsed'],
                        'total' => $window['total'],
                        'started_on' => $goal->started_on->toDateString(),
                    ];
                })
                ->values()
                ->all(),
            // Server-supplied on purpose: a Tehran user at 01:00 and a UTC server
            // disagree by a day, and that drift is invisible to a fixture.
            'today' => $today->toDateString(),
        ];
    }

    /**
     * Fields that are identical on both paths, because none of them are encrypted.
     *
     * @param  array{elapsed: int, total: int}  $window
     * @return array<string, mixed>
     */
    private function presentation(SavingsGoal $goal, string $calendar, array $window): array
    {
        $asset = $goal->asset;

        return [
            'id' => $goal->id,
            'title' => $goal->title,
            'asset' => [
                'id' => $asset->id,
                'key' => $asset->slug,
                'label' => $asset->label(),
                'icon' => $asset->icon,
                'icon_svg' => $asset->icon_svg,
                'color' => $asset->color,
                // From the asset row, not AssetType::unit() — that enum only
                // covers the six builtin slugs.
                'unit' => $asset->unit,
            ],
            'target_date' => $goal->target_date->toDateString(),
            'target_date_display' => DateFormatter::format($goal->target_date, $calendar, 'j F Y'),
            'elapsed_days' => $window['elapsed'],
            'total_days' => $window['total'],
        ];
    }

    /**
     * @return array{elapsed: int, total: int}
     */
    private function window(SavingsGoal $goal, CarbonImmutable $today): array
    {
        $start = CarbonImmutable::parse($goal->started_on->toDateString());
        $end = CarbonImmutable::parse($goal->target_date->toDateString());

        // Plain gregorian day counts even for jalali users: a target date is a
        // fixed instant, and converting to jalali to subtract months would
        // reintroduce month-length clamping for no benefit. Cast because
        // Carbon 3 returns floats here.
        return [
            'elapsed' => max(0, (int) $start->diffInDays($today)),
            'total' => max(0, (int) $start->diffInDays($end)),
        ];
    }

    /**
     * Holdings today and before each goal started, per asset.
     *
     * Both are summed from the same signed quantities the portfolio uses, so
     * disposals are already netted out and there is no second summation to
     * disagree with.
     *
     * @param  Collection<int, Investment>  $entries
     * @return Collection<int, Investment>
     */
    private function holdingsByAsset(Collection $entries): Collection
    {
        return $entries->reject(
            fn (Investment $entry): bool => $entry->quantity instanceof EncryptedValue,
        );
    }

    /**
     * @param  Collection<int, Investment>  $holdings
     */
    private function currentFor(SavingsGoal $goal, Collection $holdings): float
    {
        return (float) $holdings
            ->where('investment_asset_id', $goal->investment_asset_id)
            ->sum('quantity');
    }

    /**
     * What the user already held when the goal was set.
     *
     * Derived from entry dates rather than frozen on the goal, so backdating a
     * purchase corrects the baseline instead of leaving it permanently wrong —
     * and so the create form still works while the vault is locked.
     *
     * @param  Collection<int, Investment>  $holdings
     */
    private function baselineFor(SavingsGoal $goal, Collection $holdings): float
    {
        $startedOn = $goal->started_on->toDateString();

        return (float) $holdings
            ->where('investment_asset_id', $goal->investment_asset_id)
            ->filter(fn (Investment $entry): bool => $entry->occurred_at->toDateString() < $startedOn)
            ->sum('quantity');
    }
}
