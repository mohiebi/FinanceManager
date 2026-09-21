<?php

namespace App\Actions\Budgets;

use App\Enums\BudgetIncomeBasis;
use App\Enums\BudgetRuleType;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Category;
use App\Models\User;
use App\Support\CalendarDates;
use App\Support\Encryption\SealedField;
use App\Support\FrontendLocalization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveBudget
{
    /** More lines than anyone plans with, and a cheap ceiling on a request. */
    private const MAX_LINES = 30;

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $vaultArmed = false): array
    {
        return [
            'title' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'string', 'max:120'],
            'income_basis' => ['required', Rule::enum(BudgetIncomeBasis::class)],
            // The server cannot check a number it cannot read — the honest cost
            // of the vault, and the same trade SaveGoal makes.
            'expected_income' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'is_active' => ['nullable', 'boolean'],

            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.category_id' => ['nullable', 'integer'],
            'lines.*.rule_type' => ['required', Rule::enum(BudgetRuleType::class)],
            // Plaintext even under the vault: a share is not money, so this stays
            // checkable and the whole plan stays validatable.
            'lines.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.fixed_amount' => $vaultArmed
                ? SealedField::rules(required: false)
                : ['nullable', 'numeric', 'min:0'],
            // Rent in toman and a subscription in dollars belong in one plan, so
            // a fixed amount carries its own currency rather than inheriting the
            // budget's.
            'lines.*.currency' => ['nullable', Rule::enum(Currency::class)],
            'lines.*.rollover_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Check the plan hangs together, then seal whatever the server may not keep.
     *
     * Every rule here survives an armed vault, which is the point of storing
     * percentages in plaintext: a budget whose amounts are unreadable can still
     * be rejected for promising 130% of an income.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function normalize(
        User $user,
        array $validated,
        bool $vaultArmed = false,
        bool $creating = true,
    ): array {
        $lines = array_values($validated['lines']);

        self::assertLinesAreCoherent($user, $lines);

        if (
            BudgetIncomeBasis::from($validated['income_basis']) === BudgetIncomeBasis::Expected
            && blank($validated['expected_income'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'expected_income' => __('validation.required', ['attribute' => 'expected income']),
            ]);
        }

        $validated['lines'] = array_map(
            static function (array $line, int $index) use ($vaultArmed): array {
                $rule = BudgetRuleType::from($line['rule_type']);

                $normalized = [
                    'category_id' => $rule === BudgetRuleType::Remainder
                        ? null
                        : (int) $line['category_id'],
                    'rule_type' => $rule,
                    'percent' => $rule === BudgetRuleType::Percent ? $line['percent'] : null,
                    'fixed_amount' => $rule === BudgetRuleType::Fixed
                        ? $line['fixed_amount']
                        : null,
                    // Only a fixed amount has a currency to be in. Null on every
                    // other rule, where it would be a fact about nothing.
                    'currency' => $rule === BudgetRuleType::Fixed
                        ? ($line['currency'] ?? null)
                        : null,
                    'rollover_enabled' => (bool) ($line['rollover_enabled'] ?? false),
                    // Position is the order they arrived in. The client owns the
                    // ordering; the server just records it.
                    'sort_order' => $index,
                ];

                return $vaultArmed
                    ? SealedField::wrap($normalized, ['fixed_amount'])
                    : $normalized;
            },
            $lines,
            array_keys($lines),
        );

        if ($creating) {
            // Not user-supplied: a plan starts with the period it was written in,
            // and backdating it would invent months the user never planned.
            $validated['starts_on'] = CalendarDates::monthStart(
                $user->localToday(),
                FrontendLocalization::normalizeCalendar($user->calendar),
            )->toDateString();
        }

        return $vaultArmed
            ? SealedField::wrap($validated, ['title', 'expected_income'])
            : $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?Budget $budget = null): Budget
    {
        $lines = $data['lines'];
        unset($data['lines']);

        return DB::transaction(function () use ($user, $data, $lines, $budget): Budget {
            if (! $budget instanceof Budget) {
                $budget = $user->budgets()->create($data);
            } else {
                $budget->fill($data);
                $budget->save();
            }

            // Replaced wholesale rather than diffed. Lines carry no history in
            // this phase — the period snapshots that will need stable ids do not
            // exist yet — so a diff would be machinery with nothing to protect.
            $budget->lines()->delete();

            foreach ($lines as $line) {
                $model = new BudgetLine($line);
                $model->budget_id = $budget->id;
                // Set before saving so the encryption owner resolves from memory
                // rather than a query per line. See BudgetLine::encryptionOwnerId.
                $model->setRelation('budget', $budget);
                $model->save();
            }

            return $budget->load('lines.category');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    private static function assertLinesAreCoherent(User $user, array $lines): void
    {
        $percentTotal = 0.0;
        $remainderCount = 0;
        $categoryIds = [];

        foreach ($lines as $index => $line) {
            $rule = BudgetRuleType::from($line['rule_type']);

            if ($rule === BudgetRuleType::Remainder) {
                $remainderCount++;

                continue;
            }

            if (blank($line['category_id'] ?? null)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.category_id" => __('budgets.errors.category_required'),
                ]);
            }

            $categoryIds[] = (int) $line['category_id'];

            if ($rule === BudgetRuleType::Percent) {
                if (blank($line['percent'] ?? null)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.percent" => __('budgets.errors.percent_required'),
                    ]);
                }

                $percentTotal += (float) $line['percent'];
            }

            // Under the vault this is a presence check, not a value check: the
            // amount is ciphertext the server has no way to read.
            if ($rule === BudgetRuleType::Fixed && blank($line['fixed_amount'] ?? null)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.fixed_amount" => __('budgets.errors.amount_required'),
                ]);
            }
        }

        if ($remainderCount > 1) {
            throw ValidationException::withMessages([
                'lines' => __('budgets.errors.one_remainder'),
            ]);
        }

        // Rounded before comparing so three lines of 33.33 plus one of 0.01 are
        // not rejected for a float artefact in the fifteenth decimal.
        if (round($percentTotal, 2) > 100) {
            throw ValidationException::withMessages([
                'lines' => __('budgets.errors.percent_total', ['total' => round($percentTotal, 2)]),
            ]);
        }

        if (count($categoryIds) !== count(array_unique($categoryIds))) {
            throw ValidationException::withMessages([
                'lines' => __('budgets.errors.duplicate_category'),
            ]);
        }

        self::assertCategoriesAreSpendable($user, $categoryIds);
    }

    /**
     * @param  array<int, int>  $categoryIds
     *
     * @throws ValidationException
     */
    private static function assertCategoriesAreSpendable(User $user, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $usable = Category::query()
            ->availableFor($user)
            // Income categories are what fills a budget, not what a line can
            // spend against — a plan line pointed at one could never progress.
            ->forType(TransactionType::Cost)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->all();

        if (count($usable) === count(array_unique($categoryIds))) {
            return;
        }

        throw ValidationException::withMessages([
            'lines' => __('validation.exists', ['attribute' => 'category']),
        ]);
    }
}
