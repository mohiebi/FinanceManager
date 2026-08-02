<?php

namespace App\Mcp\Tools\Budgets;

use App\Actions\Budgets\BuildBudgetProgress;
use App\Enums\Feature;
use App\Mcp\Concerns\RequiresFeature;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the user\'s budget ("flight plan") for the current period: what each category is allowed to spend, how much of that has been spent, and how much is left. Allowances come from rules — a share of income, a fixed amount, or whatever is left over — so they move with the income actually received this period.')]
class BudgetProgressTool extends Tool
{
    use RequiresFeature;

    /**
     * @return array<int, Feature>
     */
    protected static function requiredFeatures(): array
    {
        return [Feature::Budgets];
    }

    public function __construct(private readonly BuildBudgetProgress $buildBudgetProgress) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $budget = $user->budgets()
            ->active()
            ->with('lines.category')
            ->latest('id')
            ->first();

        if (! $budget instanceof Budget) {
            return Response::structured([
                'budget' => null,
                'message' => 'No budget has been set up yet.',
            ]);
        }

        $progress = $this->buildBudgetProgress->handle($user, $budget);

        return Response::structured([
            'title' => $progress['title'],
            'currency' => $progress['currency'],
            // `actual` bases percentages on income received so far, `expected` on
            // a figure the user declared — worth reporting, because it explains
            // why an allowance is what it is.
            'income_basis' => $progress['income_basis'],
            'period' => $progress['period'],
            'income' => $progress['income'],
            'allocated' => $progress['allocated'],
            'unallocated' => $progress['unallocated'],
            'over_allocated' => $progress['over_allocated'],
            'spent' => $progress['actual'],
            'lines' => collect($progress['lines'])->map(fn (array $line): array => [
                'category' => $line['category']['name'] ?? null,
                'rule' => $line['rule_type'],
                'percent' => $line['percent'],
                'allowance' => $line['allocated'],
                'spent' => $line['actual'],
                // Negative when the line is overspent, which is the same fact as
                // `over` — kept as a number so it can be talked about.
                'remaining' => $line['remaining'],
                'over_budget' => $line['over'],
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
