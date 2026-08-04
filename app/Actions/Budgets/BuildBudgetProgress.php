<?php

namespace App\Actions\Budgets;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\BudgetIncomeBasis;
use App\Enums\BudgetRuleType;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AssetPriceService;
use App\Support\BudgetMath;
use App\Support\CalendarDates;
use App\Support\Encryption\EncryptedValue;
use App\Support\FrontendLocalization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Enumerable;

/**
 * Progress against a budget for the current period.
 *
 * A sibling of BuildGoalProgress rather than a method on it: a budget is a
 * ratio of money to money inside one month, where a goal is a ratio of asset
 * quantities across many.
 *
 * The period is resolved here on both paths, because dates are never encrypted
 * and duplicating calendar maths in the browser is how a Jalali month quietly
 * becomes a Gregorian one. Only the money is mirrored — see {@see BudgetMath}
 * and resources/js/lib/budget.ts.
 */
class BuildBudgetProgress
{
    public function __construct(
        private readonly CurrencyConverter $converter,
        private readonly AssetPriceService $prices,
    ) {}

    /**
     * The plaintext path: everything resolved server-side.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, Budget $budget, ?CarbonImmutable $today = null): array
    {
        $period = $this->period($user, $today);
        $transactions = $this->transactionsFor($user, $period);

        // Safety net, mirroring BuildGoalProgress::handle(): if a caller forgets
        // the vault branch, drop the row rather than treat ciphertext as a
        // number and report a confidently wrong allowance.
        $readable = $transactions->reject(
            fn (Transaction $transaction): bool => $transaction->amount instanceof EncryptedValue,
        );

        $income = $this->incomeFor($budget, $readable);
        $claimedCategoryIds = $this->claimedCategoryIds($budget);

        $lines = $budget->lines->map(fn (BudgetLine $line): array => [
            'rule' => $line->rule_type->value,
            'percent' => (float) ($line->percent ?? 0),
            // Converted into the budget's currency: a line written in dollars
            // and a plan totalled in toman have to meet somewhere, and it is
            // the plan's currency that every figure on the page is labelled in.
            'fixed' => $line->fixed_amount instanceof EncryptedValue
                ? 0.0
                : $this->converter->convert(
                    (float) ($line->fixed_amount ?? 0),
                    $line->currencyWithin($budget),
                    $budget->currency,
                ),
            'actual' => $this->spentOn($budget, $line, $readable, $claimedCategoryIds),
        ])->values()->all();

        $allowances = BudgetMath::compute($income, $lines);

        return [
            ...$this->presentation($budget, $period),
            ...$allowances,
            'lines' => $this->mergeLinePresentation($budget, $allowances['lines']),
        ];
    }

    /**
     * The armed path: the plan's structure, the period, and the raw rows.
     *
     * Everything money-shaped stays sealed — the fixed amounts, the expected
     * income, and every transaction in the period. The browser decrypts, sums,
     * and runs BudgetMath's port. Rates travel too: they are public market
     * prices, so shipping them costs nothing in privacy, and without them a
     * multi-currency month cannot be totalled at all.
     *
     * @return array<string, mixed>
     */
    public function clientPayload(User $user, Budget $budget, ?CarbonImmutable $today = null): array
    {
        $period = $this->period($user, $today);
        $transactions = $this->transactionsFor($user, $period);
        $claimedCategoryIds = $this->claimedCategoryIds($budget);

        return [
            ...$this->presentation($budget, $period),
            'expected_income' => $budget->expected_income,
            'lines' => $budget->lines->map(fn (BudgetLine $line): array => [
                ...$this->linePresentation($line),
                'fixed_amount' => $line->fixed_amount,
                // The currency is not a secret, only the amount is — so the
                // browser is told what to convert from without holding a key.
                'currency' => $line->currencyWithin($budget)->value,
                // Which rows this line owns, resolved server-side: category
                // assignment is not a secret, and working it out here keeps the
                // "everything not claimed above" rule in one place.
                'category_ids' => $this->categoryIdsFor($line, $claimedCategoryIds),
            ])->all(),
            'transactions' => $transactions->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'category_id' => $transaction->category_id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency->value,
            ])->values()->all(),
            'rates' => [
                'tomanPerUsd' => $this->prices->priceFor(AssetType::Usd),
                'tomanPerEur' => $this->prices->priceFor(AssetType::Eur),
            ],
        ];
    }

    /**
     * The current period, in the user's own calendar.
     *
     * Runs from the first of the month to today rather than to the month's end,
     * matching what the reports page calls "this month" — a plan that counted
     * income the user has not received yet would set targets against money that
     * does not exist.
     *
     * @return array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     month_end: CarbonImmutable,
     *     label: string,
     *     day_of_month: int,
     *     days_in_month: int,
     * }
     */
    public function period(User $user, ?CarbonImmutable $today = null): array
    {
        $today = $today ?? $user->localToday();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);

        $start = CalendarDates::monthStart($today, $calendar);
        $descriptor = CalendarDates::monthDescriptor($today, $calendar);

        return [
            'start' => $start,
            'end' => $today,
            'month_end' => $start->addDays($descriptor['days_in_month'] - 1),
            'label' => $descriptor['label'],
            'day_of_month' => $descriptor['day_of_month'],
            'days_in_month' => $descriptor['days_in_month'],
        ];
    }

    /**
     * Fields that are identical on both paths, because none of them are encrypted.
     *
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    private function presentation(Budget $budget, array $period): array
    {
        return [
            'id' => $budget->id,
            'title' => $budget->title,
            'income_basis' => $budget->income_basis->value,
            'currency' => $budget->currency->value,
            'period' => [
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
                'month_end' => $period['month_end']->toDateString(),
                'label' => $period['label'],
                'day_of_month' => $period['day_of_month'],
                'days_in_month' => $period['days_in_month'],
                'days_remaining' => max(0, $period['days_in_month'] - $period['day_of_month']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linePresentation(BudgetLine $line): array
    {
        $category = $line->category;

        return [
            'id' => $line->id,
            'rule_type' => $line->rule_type->value,
            'percent' => $line->percent === null ? null : (float) $line->percent,
            'rollover_enabled' => $line->rollover_enabled,
            'category' => $category === null ? null : [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $allowances
     * @return array<int, array<string, mixed>>
     */
    private function mergeLinePresentation(Budget $budget, array $allowances): array
    {
        return $budget->lines
            ->values()
            ->map(fn (BudgetLine $line, int $index): array => [
                ...$this->linePresentation($line),
                ...$allowances[$index],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $period
     * @return Collection<int, Transaction>
     */
    private function transactionsFor(User $user, array $period): Collection
    {
        return $user->transactions()
            ->whereDate('occurred_at', '>=', $period['start']->toDateString())
            ->whereDate('occurred_at', '<=', $period['end']->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function incomeFor(Budget $budget, Collection $transactions): float
    {
        if ($budget->income_basis === BudgetIncomeBasis::Expected) {
            return $budget->expected_income instanceof EncryptedValue
                ? 0.0
                : (float) ($budget->expected_income ?? 0);
        }

        return $this->sum($budget, $transactions->where('type', TransactionType::Income));
    }

    /**
     * What has actually been spent against one line.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @param  array<int, int>  $claimedCategoryIds
     */
    private function spentOn(
        Budget $budget,
        BudgetLine $line,
        Collection $transactions,
        array $claimedCategoryIds,
    ): float {
        $costs = $transactions->where('type', TransactionType::Cost);

        // Only a remainder line may own uncategorised spending. A category line
        // that somehow lost its category matches nothing rather than quietly
        // hoovering up every row with a null category — which is what it did,
        // and it read as though the line were tracking something.
        if ($line->rule_type !== BudgetRuleType::Remainder && $line->category_id === null) {
            return 0.0;
        }

        // "Everything else" is exactly that: spending no other line has claimed,
        // including rows with no category at all.
        $matching = $line->rule_type === BudgetRuleType::Remainder
            ? $costs->reject(fn (Transaction $transaction): bool => in_array(
                (int) $transaction->category_id,
                $claimedCategoryIds,
                true,
            ))
            : $costs->where('category_id', $line->category_id);

        return $this->sum($budget, $matching);
    }

    /**
     * Category ids a line owns, for the browser to filter on.
     *
     * A remainder line owns none of them and is instead identified by the
     * `remainder` rule — the browser subtracts the claimed set, the same way
     * {@see self::spentOn()} does.
     *
     * @param  array<int, int>  $claimedCategoryIds
     * @return array<int, int>
     */
    private function categoryIdsFor(BudgetLine $line, array $claimedCategoryIds): array
    {
        return $line->rule_type === BudgetRuleType::Remainder
            ? $claimedCategoryIds
            : [(int) $line->category_id];
    }

    /**
     * @return array<int, int>
     */
    private function claimedCategoryIds(Budget $budget): array
    {
        return $budget->lines
            ->reject(fn (BudgetLine $line): bool => $line->category_id === null)
            ->map(fn (BudgetLine $line): int => (int) $line->category_id)
            ->values()
            ->all();
    }

    /**
     * @param  Enumerable<int, Transaction>  $transactions
     */
    private function sum(Budget $budget, Enumerable $transactions): float
    {
        $total = 0.0;

        foreach ($transactions as $transaction) {
            $total += $this->converter->convert(
                $transaction->amount,
                $transaction->currency,
                $budget->currency,
            );
        }

        return round($total, 2);
    }
}
