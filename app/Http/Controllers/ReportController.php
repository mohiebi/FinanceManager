<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
use App\Support\DateFormatter;
use App\Support\FrontendLocalization;
use App\Support\TransactionListing;
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
        $vaultArmed = $request->user()->vaultIsArmed();
        $calendar = FrontendLocalization::normalizeCalendar($request->user()?->calendar);
        $selectedType = $this->resolveTransactionType((string) $request->query('type'));
        $selectedCategoryId = $this->resolveCategoryId($request);
        $search = trim((string) $request->query('search'));
        $excludeInvestments = $request->boolean('exclude_investments');
        $investmentCategoryIds = $this->investmentCostCategoryIds($request);
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

        $query = $request->user()
            ->transactions()
            ->with('category:id,parent_id,name,slug,type,for_both_types,color,is_default')
            ->whereDate('occurred_at', '>=', $fromDate->toDateString())
            ->whereDate('occurred_at', '<=', $toDate->toDateString())
            ->when(
                $selectedType instanceof TransactionType,
                fn (Builder $query) => $query->where('type', $selectedType),
            )
            ->when(
                $selectedCategoryId !== null,
                // A parent stands for its whole family: filtering by Food that
                // hid every Restaurant row would read as missing spending.
                fn (Builder $query) => $query->whereIn(
                    'category_id',
                    Category::familiesFor($request->user(), [$selectedCategoryId])[$selectedCategoryId],
                ),
            )
            // Money moved into assets is not spending. Excluded from the query
            // rather than subtracted from the total, so the tables, the charts and
            // the KPI cards cannot disagree about what is in the period.
            ->when(
                $excludeInvestments && $investmentCategoryIds !== [],
                fn (Builder $query) => $query->whereNotIn('category_id', $investmentCategoryIds),
            )
            ->latest('occurred_at')
            ->latest();

        // Searched in PHP: title and description are encrypted at rest, so a SQL
        // LIKE against them matches nothing.
        $transactions = TransactionListing::search($query->get(), $search);

        $costs = $transactions
            ->where('type', TransactionType::Cost)
            ->values();

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->values();

        $costPage = max(1, (int) $request->query('cost_page', 1));
        $incomePage = max(1, (int) $request->query('income_page', 1));

        // Paged from the filtered collections rather than the builder, so search
        // and paging agree on the same result set.
        $costPaginator = TransactionListing::paginate($costs, $costPage);
        $incomePaginator = TransactionListing::paginate($incomes, $incomePage);

        return Inertia::render('Report', [
            'filters' => [
                'range' => $selectedRange,
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'search' => $search,
                'type' => $selectedType?->value ?? 'all',
                'category' => $selectedCategoryId,
                'exclude_investments' => $excludeInvestments,
            ],
            // Drives the label on the costs card, and hides the toggle entirely
            // for anyone with no investment category to exclude.
            'hasInvestmentCategory' => $investmentCategoryIds !== [],
            'period' => [
                'label' => $this->makePeriodLabel($selectedRange, $fromDate, $toDate, $calendar),
            ],
            'transactions' => [
                'costs' => $this->transformTransactions(
                    $costPaginator->getCollection(),
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                    $vaultArmed,
                ),
                'incomes' => $this->transformTransactions(
                    $incomePaginator->getCollection(),
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                    $vaultArmed,
                ),
                'meta' => [
                    'costs' => [
                        'current_page' => $costPaginator->currentPage(),
                        'last_page' => $costPaginator->lastPage(),
                        'total' => $costPaginator->total(),
                    ],
                    'incomes' => [
                        'current_page' => $incomePaginator->currentPage(),
                        'last_page' => $incomePaginator->lastPage(),
                        'total' => $incomePaginator->total(),
                    ],
                ],
            ],
            'analyticsTransactions' => [
                'costs' => $this->transformTransactions(
                    $costs,
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                    $vaultArmed,
                ),
                'incomes' => $this->transformTransactions(
                    $incomes,
                    $request,
                    $currencyConverter,
                    $selectedCurrency,
                    $vaultArmed,
                ),
            ],
            'categories' => CategoryResource::groupedByType($categories, $request),
            'currencies' => collect(Currency::cases())
                ->map(fn (Currency $currency) => [
                    'label' => $currency->label(),
                    'value' => $currency->value,
                ]),
            'selectedCurrency' => $selectedCurrency->value,
            // Rates rather than totals when the server cannot read the amounts.
            // Public market prices, so shipping them costs nothing in privacy —
            // and a total of zero is a far worse answer than none.
            'rates' => $vaultArmed ? $this->displayRates() : null,
            'summary' => [
                'cost' => $vaultArmed ? null : $currencyConverter->sumFormatted($costs, $selectedCurrency),
                'income' => $vaultArmed ? null : $currencyConverter->sumFormatted($incomes, $selectedCurrency),
                // The row count is not a secret — only the money is.
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

    /**
     * Cost categories that count as an investment rather than as spending.
     *
     * Both the seeded default and any of the user's own carrying the same slug —
     * someone who made their own "Investment" category before this shipped should
     * not have to recategorise to use the filter.
     *
     * Scoped to cost categories on purpose. The seeded income `investment` category
     * is gone, but nothing stops a user creating their own with the same slug, and
     * dropping income rows is not what a filter about spending should do.
     *
     * @return array<int, int>
     */
    private function investmentCostCategoryIds(Request $request): array
    {
        return Category::query()
            ->availableFor($request->user())
            ->where('type', TransactionType::Cost)
            ->where('slug', 'investment')
            ->pluck('id')
            ->all();
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
        bool $vaultArmed,
    ): SupportCollection {
        return $transactions->map(fn (Transaction $transaction) => [
            ...(new TransactionResource($transaction))->resolve($request),
            // Null under the vault: the amount is ciphertext, and casting it would
            // silently produce a converted zero. The browser converts instead.
            'display_amount' => $vaultArmed ? null : $currencyConverter->format(
                $transaction->amount,
                $transaction->currency,
                $selectedCurrency,
            ),
            'display_currency' => $selectedCurrency->value,
        ]);
    }

    /**
     * @return array{tomanPerUsd: float, tomanPerEur: float}
     */
    private function displayRates(): array
    {
        $prices = app(AssetPriceService::class);

        return [
            'tomanPerUsd' => $prices->priceFor(AssetType::Usd),
            'tomanPerEur' => $prices->priceFor(AssetType::Eur),
        ];
    }
}
