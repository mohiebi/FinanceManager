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
#[Description('Summarize the user\'s spending and income for a date range, grouped by category or by month. Dates may be Gregorian or Jalali (auto-detected). Monthly grouping follows the user\'s calendar preference. Amounts in other currencies are converted to the requested currency (defaults to the user\'s preferred currency).')]
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
            ->with('category')
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
                : ($transaction->category?->name ?? 'Uncategorized');

            $groups[$key] ??= ['cost' => 0.0, 'income' => 0.0, 'count' => 0];
            $groups[$key][$transaction->type->value] += $converted;
            $groups[$key]['count']++;
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
