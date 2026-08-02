<?php

namespace App\Mcp\Tools\Goals;

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Models\User;
use App\Support\CalendarDates;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s savings goals and how far along each one is. Goals are denominated in an asset unit — grams of gold, dollars, units of a crypto — not in money, so progress is a ratio of quantities and needs no price. Each goal reports whether it is reached, whether it is on pace for its target date, and how much per day would get there.')]
class ListSavingsGoalsTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Goals];
    }

    public function __construct(
        private readonly BuildGoalProgress $buildGoalProgress,
        private readonly BuildPortfolioBreakdown $buildPortfolioBreakdown,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $isJalali = CalendarDates::isJalaliUser($user);

        // The same signed quantities the portfolio uses, so disposals are already
        // netted out and there is no second summation to disagree with.
        $entries = $this->buildPortfolioBreakdown->entriesFor($user);
        $goals = $this->buildGoalProgress->handle($user, $entries);

        if ($goals === []) {
            return Response::structured([
                'goals' => [],
                'message' => 'No savings goals have been set up yet.',
            ]);
        }

        return Response::structured([
            'goals' => collect($goals)->map(fn (array $goal): array => [
                'id' => $goal['id'],
                'title' => $goal['title'],
                'asset' => $goal['asset']['label'],
                'unit' => $goal['asset']['unit'],
                'current_quantity' => $goal['current_quantity'],
                'target_quantity' => $goal['target_quantity'],
                'progress_percent' => $goal['progress'] === null
                    ? null
                    : round($goal['progress'] * 100, 2),
                // Distinct facts: `reached` is about the target, `on_track` is
                // only about the schedule. A goal can be reached early, or on
                // pace and nowhere near done.
                'reached' => $goal['reached'],
                'on_track' => $goal['on_track'],
                'target_date' => $goal['target_date'],
                ...($isJalali ? ['target_date_jalali' => CalendarDates::toJalali($goal['target_date'])] : []),
                'days_remaining' => $goal['days_remaining'],
                'required_per_day' => $goal['required_per_day'],
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
