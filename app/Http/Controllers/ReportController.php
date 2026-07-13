<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\CurrencyPreference;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;
use Morilog\Jalali\Jalalian;
use Throwable;

class ReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, CurrencyConverter $currencyConverter): Response
    {
        $selectedRange = $this->resolveRange((string) $request->query('range'));
        $selectedCurrency = CurrencyPreference::resolve($request);
        $calendar = FrontendLocalization::normalizeCalendar($request->user()?->calendar);
        $selectedType = $this->resolveTransactionType((string) $request->query('type'));
        $selectedCategoryId = $this->resolveCategoryId($request);
        $search = trim((string) $request->query('search'));
        [$fromDate, $toDate] = $this->resolveDateRange(
            $selectedRange,
            (string) $request->query('from'),
            (string) $request->query('to'),
            $calendar,
        );

        $categories = Category::query()
            ->availableFor($request->user())
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $transactions = $request->user()
            ->transactions()
            ->with('category:id,name,slug,type,is_default')
            ->whereDate('occurred_at', '>=', $fromDate->toDateString())
            ->whereDate('occurred_at', '<=', $toDate->toDateString())
            ->when(
                $selectedType instanceof TransactionType,
                fn (Builder $query) => $query->where('type', $selectedType),
            )
            ->when(
                $selectedCategoryId !== null,
                fn (Builder $query) => $query->where('category_id', $selectedCategoryId),
            )
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }),
            )
            ->latest('occurred_at')
            ->latest()
            ->get();

        $costs = $transactions
            ->where('type', TransactionType::Cost)
            ->values();

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->values();

        return Inertia::render('Report', [
            'filters' => [
                'range' => $selectedRange,
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'search' => $search,
                'type' => $selectedType?->value ?? 'all',
                'category' => $selectedCategoryId,
            ],
            'period' => [
                'label' => $this->makePeriodLabel($selectedRange, $fromDate, $toDate, $calendar),
            ],
            'transactions' => [
                'costs' => $this->transformTransactions(
                    $costs,
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                ),
                'incomes' => $this->transformTransactions(
                    $incomes,
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                ),
            ],
            'categories' => [
                'cost' => $categories
                    ->where('type', TransactionType::Cost)
                    ->values()
                    ->map(fn (Category $category) => (new CategoryResource($category))->resolve($request)),
                'income' => $categories
                    ->where('type', TransactionType::Income)
                    ->values()
                    ->map(fn (Category $category) => (new CategoryResource($category))->resolve($request)),
            ],
            'currencies' => collect(Currency::cases())
                ->map(fn (Currency $currency) => [
                    'label' => $currency->label(),
                    'value' => $currency->value,
                ]),
            'selectedCurrency' => $selectedCurrency->value,
            'summary' => [
                'cost' => $currencyConverter->sumFormatted($costs, $selectedCurrency),
                'income' => $currencyConverter->sumFormatted($incomes, $selectedCurrency),
                'count' => $transactions->count(),
            ],
        ]);
    }

    private function resolveRange(string $range): string
    {
        return in_array($range, ['this_month', 'this_season', 'yearly', 'custom'], true)
            ? $range
            : 'this_month';
    }

    private function resolveTransactionType(string $type): ?TransactionType
    {
        return TransactionType::tryFrom($type);
    }

    private function resolveCategoryId(Request $request): ?int
    {
        $categoryId = (int) $request->query('category');

        if ($categoryId < 1) {
            return null;
        }

        if (! Category::query()
            ->availableFor($request->user())
            ->whereKey($categoryId)
            ->exists()) {
            return null;
        }

        return $categoryId;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(string $range, string $fromInput, string $toInput, string $calendar): array
    {
        $today = Carbon::today();

        if ($calendar === 'jalali' && $range !== 'custom') {
            return $this->resolveJalaliDateRange($range, $today);
        }

        return match ($range) {
            'this_season' => [$today->copy()->startOfQuarter(), $today->copy()],
            'yearly' => [$today->copy()->startOfYear(), $today->copy()],
            'custom' => $this->resolveCustomDateRange($fromInput, $toInput, $today),
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveJalaliDateRange(string $range, Carbon $today): array
    {
        $jalaliToday = Jalalian::fromCarbon($today);
        $year = $jalaliToday->getYear();
        $month = $jalaliToday->getMonth();

        $fromDate = match ($range) {
            'this_season' => $this->jalaliDateToCarbon($year, $this->jalaliSeasonStartMonth($month), 1),
            'yearly' => $this->jalaliDateToCarbon($year, 1, 1),
            default => $this->jalaliDateToCarbon($year, $month, 1),
        };

        return [$fromDate, $today->copy()];
    }

    private function jalaliSeasonStartMonth(int $month): int
    {
        return (intdiv($month - 1, 3) * 3) + 1;
    }

    private function jalaliDateToCarbon(int $year, int $month, int $day): Carbon
    {
        return Carbon::instance((new Jalalian($year, $month, $day))->toCarbon())->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveCustomDateRange(string $fromInput, string $toInput, Carbon $today): array
    {
        $fromDate = $this->parseDate($fromInput) ?? $today->copy()->startOfMonth();
        $toDate = $this->parseDate($toInput) ?? $today->copy();

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function makePeriodLabel(string $range, Carbon $fromDate, Carbon $toDate, string $calendar): string
    {
        return match ($range) {
            'this_season' => sprintf('This season (%s)', DateFormatter::format($fromDate, $calendar, 'Y-m')),
            'yearly' => sprintf('Year to date (%s)', DateFormatter::format($fromDate, $calendar, 'Y')),
            'custom' => sprintf(
                'Custom range: %s to %s',
                DateFormatter::format($fromDate, $calendar, 'Y-m-d'),
                DateFormatter::format($toDate, $calendar, 'Y-m-d'),
            ),
            default => DateFormatter::format($fromDate, $calendar, 'Y-m'),
        };
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return SupportCollection<int, array<string, mixed>>
     */
    private function transformTransactions(
        Collection $transactions,
        Request $request,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
    ): SupportCollection {
        return $transactions->map(fn (Transaction $transaction) => [
            ...(new TransactionResource($transaction))->resolve($request),
            'display_amount' => $currencyConverter->format(
                $transaction->amount,
                $transaction->currency,
                $selectedCurrency,
            ),
            'display_currency' => $selectedCurrency->value,
        ]);
    }
}
