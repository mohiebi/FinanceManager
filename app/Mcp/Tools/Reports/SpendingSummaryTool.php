<?php

namespace App\Mcp\Tools\Reports;

use App\Actions\Transactions\CurrencyConverter;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CalendarDates;
use App\Support\CurrencyPreference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Summarize the user\'s spending and income for a date range, grouped by category or by month. Dates may be Gregorian or Jalali (auto-detected). Monthly grouping follows the user\'s calendar preference. Amounts in other currencies are converted to the requested currency (defaults to the user\'s preferred currency). Grouped by category, a subcategory\'s amounts count toward its parent\'s group, and each group\'s `subcategories` breaks those out; whatever the subcategories do not cover was recorded on the parent itself.')]
class SpendingSummaryTool extends Tool
{
    public function __construct(private readonly CurrencyConverter $currencyConverter) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        // Jalali input dates are converted server-side before validation so
        // they are never misread as ancient Gregorian dates.
        $request->merge([
            'from_date' => CalendarDates::normalizeToGregorian($request->get('from_date')),
            'to_date' => CalendarDates::normalizeToGregorian($request->get('to_date')),
        ]);

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'group_by' => ['nullable', 'string', 'in:category,month'],
            'currency' => ['nullable', 'string', 'in:toman,usd,eur'],
        ]);

        $currency = CurrencyPreference::resolveFor($user, $validated['currency'] ?? null);
        $groupBy = $validated['group_by'] ?? 'category';
        $isJalali = CalendarDates::isJalaliUser($user);

        // Multi-currency amounts cannot be summed in SQL; convert per row.
        $transactions = $user->transactions()
            ->with('category.parent:id,name')
            ->whereDate('occurred_at', '>=', $validated['from_date'])
            ->whereDate('occurred_at', '<=', $validated['to_date'])
            ->get();

        $groups = [];
        $totals = ['cost' => 0.0, 'income' => 0.0];

        foreach ($transactions as $transaction) {
            /** @var Transaction $transaction */
            $converted = $this->currencyConverter->convert(
                (float) $transaction->amount,
                $transaction->currency,
                $currency,
            );

            // Monthly buckets follow the user's calendar: a Jalali month spans
            // two Gregorian months, so Gregorian buckets would misalign.
            $key = $groupBy === 'month'
                ? ($isJalali
                    ? CalendarDates::jalaliMonthKey($transaction->occurred_at)
                    : $transaction->occurred_at->format('Y-m'))
                : ($transaction->category?->parent?->name ?? $transaction->category?->name ?? 'Uncategorized');

            $groups[$key] ??= ['cost' => 0.0, 'income' => 0.0, 'count' => 0, 'subcategories' => []];
            $groups[$key][$transaction->type->value] += $converted;
            $groups[$key]['count']++;

            // Rolled up into the parent above; kept apart here as well so the
            // family can still be read line by line.
            if ($groupBy === 'category' && $transaction->category?->parent_id !== null) {
                $child = $transaction->category->name;
                $groups[$key]['subcategories'][$child] ??= ['cost' => 0.0, 'income' => 0.0, 'count' => 0];
                $groups[$key]['subcategories'][$child][$transaction->type->value] += $converted;
                $groups[$key]['subcategories'][$child]['count']++;
            }
            $totals[$transaction->type->value] += $converted;
        }

        ksort($groups);

        return Response::structured([
            'currency' => $currency->value,
            'calendar' => $isJalali ? 'jalali' : 'gregorian',
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'from_date_jalali' => $isJalali ? CalendarDates::toJalali($validated['from_date']) : null,
            'to_date_jalali' => $isJalali ? CalendarDates::toJalali($validated['to_date']) : null,
            'group_by' => $groupBy,
            'month_calendar' => $groupBy === 'month' ? ($isJalali ? 'jalali' : 'gregorian') : null,
            'groups' => collect($groups)->map(fn (array $group, string $key): array => [
                'group' => $key,
                'cost' => round($group['cost'], 2),
                'income' => round($group['income'], 2),
                'count' => $group['count'],
                ...($groupBy === 'category' ? [
                    'subcategories' => collect($group['subcategories'])
                        ->map(fn (array $child, string $name): array => [
                            'group' => $name,
                            'cost' => round($child['cost'], 2),
                            'income' => round($child['income'], 2),
                            'count' => $child['count'],
                        ])
                        ->sortKeys()
                        ->values()
                        ->all(),
                ] : []),
            ])->values()->all(),
            'total_cost' => round($totals['cost'], 2),
            'total_income' => round($totals['income'], 2),
            'net' => round($totals['income'] - $totals['cost'], 2),
            'transaction_count' => $transactions->count(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from_date' => $schema->string()->description('Start of the range (YYYY-MM-DD). Gregorian or Jalali — Jalali years (1100-1599) are auto-detected and converted server-side; pass the user\'s Jalali dates unchanged.')->required(),
            'to_date' => $schema->string()->description('End of the range (YYYY-MM-DD). Gregorian or Jalali, auto-detected.')->required(),
            'group_by' => $schema->string()->enum(['category', 'month'])->description('Group results by category (default) or by month in the user\'s calendar.'),
            'currency' => $schema->string()->enum(['toman', 'usd', 'eur'])->description('Currency for converted amounts. Defaults to the user\'s preferred currency.'),
        ];
    }
}
