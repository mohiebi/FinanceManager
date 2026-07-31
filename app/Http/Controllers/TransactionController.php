<?php

namespace App\Http\Controllers;

use App\Actions\Gamification\AwardMilestones;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Actions\Transactions\CurrencyConverter;
use App\Actions\Transactions\SaveTransaction;
use App\Enums\AssetType;
use App\Enums\Currency;
use App\Enums\Feature;
use App\Enums\TransactionType;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\BillOccurrence;
use App\Models\Category;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
use App\Support\FrontendLocalization;
use App\Support\LogbookCompleteness;
use App\Support\StreakCalculator;
use App\Support\TransactionListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Morilog\Jalali\Jalalian;
use Throwable;

class TransactionController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function dashboard(
        Request $request,
        CurrencyConverter $currencyConverter,
        BuildPortfolioBreakdown $breakdownBuilder,
        AssetPriceService $priceService,
        StreakCalculator $streakCalculator,
        LogbookCompleteness $completeness,
        AwardMilestones $awardMilestones,
    ): Response {
        $user = $request->user();
        $selectedCurrency = CurrencyPreference::resolve($request);
        $features = $user->featureSet();
        $vaultArmed = $user->vaultIsArmed();

        $trendMonths = $this->trendMonths($request);
        $trendTransactions = $this->trendTransactions($request, $trendMonths);

        $moduleProps = [
            'monthlyTrend' => $this->monthlyTrend(
                $trendMonths,
                $trendTransactions,
                $currencyConverter,
                $selectedCurrency,
                $vaultArmed,
            ),
        ];

        // The trend covers three months while the workspace loads only the current
        // one, so under the vault the extra rows have to travel for the browser to
        // bucket them itself.
        if ($vaultArmed) {
            $moduleProps['trendTransactions'] = $trendTransactions
                ->map(fn (Transaction $transaction): array => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency->value,
                    'occurred_at' => $transaction->occurred_at->toDateString(),
                ])
                ->values()
                ->all();
        }

        // Not deferred: one indexed query over plaintext dates, and the card sits
        // above the fold. Deferring would trade a cheap query for a visible pop-in.
        if ($features->enabled(Feature::Gamification)) {
            $streak = $streakCalculator->for($user);
            $logbook = $completeness->for($user);

            // Nothing writes when these become true, so they are recognised on the
            // next visit — which is when the user is there to see it anyway.
            $awardMilestones->afterDashboard($user, $streak, $completeness->previousMonthWasComplete($user));

            $moduleProps['streak'] = $streak->toArray();
            $moduleProps['logbook'] = $logbook;
        }

        if ($features->enabled(Feature::Bills)) {
            $moduleProps['upcomingBills'] = $this->upcomingBills($request, $currencyConverter, $selectedCurrency, $vaultArmed);
        }

        // Deferred: these hit the price cache (and possibly tgju on a cold cache),
        // so they load in a background request after first paint — same pattern as
        // the Portfolio page. Omitting the key also drops it from Inertia's deferred
        // manifest, so no follow-up request fires for a module that is switched off.
        if ($features->enabled(Feature::Portfolio)) {
            // Under the vault the snapshot is assembled in the browser from the same
            // payload the Portfolio page uses, so only the raw entries travel here.
            $moduleProps['portfolio'] = Inertia::defer(fn () => $vaultArmed ? null : $breakdownBuilder->snapshot($user, $selectedCurrency));

            if ($vaultArmed) {
                $moduleProps['vaultPortfolio'] = Inertia::defer(fn () => $breakdownBuilder->clientPayload($user, $selectedCurrency));
            }
        }

        if ($features->enabled(Feature::Investments)) {
            $moduleProps['assetPrices'] = Inertia::defer(fn () => $this->headlinePrices($priceService, $breakdownBuilder, $selectedCurrency));
            $moduleProps['pricesSyncedAt'] = $priceService->lastSyncedAt();
        }

        return $this->renderTransactionWorkspace($request, $currencyConverter, 'Dashboard', false, $moduleProps);
    }

    /**
     * A transaction row for the page.
     *
     * With the vault armed the amount is ciphertext, so no server-side conversion
     * is possible — and a converted zero would read as "you spent nothing", which
     * is worse than sending nothing at all. The browser converts instead, using
     * the rates shipped alongside.
     *
     * @return array<string, mixed>
     */
    private function presentTransaction(
        Transaction $transaction,
        Request $request,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        bool $vaultArmed,
    ): array {
        return [
            ...(new TransactionResource($transaction))->resolve($request),
            'display_amount' => $vaultArmed
                ? null
                : $currencyConverter->format($transaction->amount, $transaction->currency, $selectedCurrency),
            'display_currency' => $selectedCurrency->value,
        ];
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, CurrencyConverter $currencyConverter): Response
    {
        return $this->renderTransactionWorkspace($request, $currencyConverter, 'Transactions', true);
    }

    /**
     * @param  array<string, mixed>  $extraProps
     */
    private function renderTransactionWorkspace(
        Request $request,
        CurrencyConverter $currencyConverter,
        string $component,
        bool $withFilters,
        array $extraProps = [],
    ): Response {
        $user = $request->user();
        $calendar = FrontendLocalization::normalizeCalendar($user->calendar);
        $selectedCurrency = CurrencyPreference::resolve($request);
        $vaultArmed = $user->vaultIsArmed();
        $selectedType = $withFilters
            ? $this->resolveTransactionType((string) $request->query('type'))
            : null;
        $selectedCategoryId = $withFilters
            ? $this->resolveCategoryId($request)
            : null;
        // `category=none` is the one non-numeric value the filter accepts, so the
        // logbook's uncategorised count has somewhere to send the user.
        $uncategorisedOnly = $withFilters && $request->query('category') === 'none';
        $search = $withFilters ? trim((string) $request->query('search')) : '';
        $fromDate = $withFilters ? $this->parseDate((string) $request->query('from')) : null;
        $toDate = $withFilters ? $this->parseDate((string) $request->query('to')) : null;

        // Default to current calendar month when no date range is explicitly set
        if (! $fromDate instanceof Carbon && ! $toDate instanceof Carbon) {
            [$fromDate, $toDate] = $this->currentMonthRange($calendar);
        }

        if ($fromDate instanceof Carbon && $toDate instanceof Carbon && $fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $query = $user->transactions()
            ->with('category:id,name,slug,type,is_default')
            ->when(
                $selectedType instanceof TransactionType,
                fn (Builder $query) => $query->where('type', $selectedType),
            )
            ->when(
                $selectedCategoryId !== null,
                fn (Builder $query) => $query->where('category_id', $selectedCategoryId),
            )
            ->when(
                $uncategorisedOnly,
                fn (Builder $query) => $query->whereNull('category_id'),
            )
            ->when(
                $fromDate instanceof Carbon,
                fn (Builder $query) => $query->whereDate('occurred_at', '>=', $fromDate->toDateString()),
            )
            ->when(
                $toDate instanceof Carbon,
                fn (Builder $query) => $query->whereDate('occurred_at', '<=', $toDate->toDateString()),
            )
            ->latest('occurred_at')
            ->latest();

        // Full collection is always needed for accurate multi-currency summary totals
        // — and, since title/description are encrypted, for search as well.
        $allTransactions = TransactionListing::search($query->get(), $search);

        $categories = Category::query()
            ->availableFor($user)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $costs = $allTransactions
            ->where('type', TransactionType::Cost)
            ->values();

        $incomes = $allTransactions
            ->where('type', TransactionType::Income)
            ->values();

        // Paginate each table independently when on the Transactions page
        $displayCosts = $costs;
        $displayIncomes = $incomes;
        $paginationMeta = null;

        if ($withFilters) {
            $costPage = max(1, (int) $request->query('cost_page', 1));
            $incomePage = max(1, (int) $request->query('income_page', 1));

            // Paged from the already-filtered collection, not the builder: once the
            // search runs in PHP, a database paginator would be paging a different
            // (unfiltered) result set.
            $costPaginator = TransactionListing::paginate($costs, $costPage);
            $incomePaginator = TransactionListing::paginate($incomes, $incomePage);

            $displayCosts = collect($costPaginator->items());
            $displayIncomes = collect($incomePaginator->items());

            $paginationMeta = [
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
            ];
        }

        return Inertia::render($component, [
            'filters' => [
                'search' => $search,
                'type' => $selectedType?->value ?? 'all',
                'category' => $uncategorisedOnly ? 'none' : $selectedCategoryId,
                'from' => $fromDate?->toDateString() ?? '',
                'to' => $toDate?->toDateString() ?? '',
            ],
            'transactions' => [
                'costs' => $displayCosts->map(
                    fn (Transaction $transaction) => $this->presentTransaction($transaction, $request, $currencyConverter, $selectedCurrency, $vaultArmed),
                ),
                'incomes' => $displayIncomes->map(
                    fn (Transaction $transaction) => $this->presentTransaction($transaction, $request, $currencyConverter, $selectedCurrency, $vaultArmed),
                ),
                'meta' => $paginationMeta,
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
            // Rates rather than totals when the server cannot read the amounts.
            // These are public market prices, not user data, so shipping them
            // costs nothing — and a wrong total is worse than no total.
            'rates' => $vaultArmed ? $this->displayRates() : null,
            'summary' => $vaultArmed ? null : [
                'cost' => $currencyConverter->sumFormatted($costs, $selectedCurrency),
                'income' => $currencyConverter->sumFormatted($incomes, $selectedCurrency),
                'count' => $allTransactions->count(),
            ],
            'transactionCount' => $allTransactions->count(),
            'period' => $this->currentPeriod($calendar),
            ...$extraProps,
        ]);
    }

    /**
     * The next few unpaid bill occurrences, soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingBills(Request $request, CurrencyConverter $currencyConverter, Currency $selectedCurrency, bool $vaultArmed): array
    {
        $today = Carbon::today();

        return BillOccurrence::query()
            ->whereNull('paid_at')
            ->whereHas('bill', fn (Builder $query) => $query
                ->where('user_id', $request->user()->id)
                ->where('is_active', true))
            ->with(['bill.category'])
            ->orderBy('due_date')
            ->limit(3)
            ->get()
            ->filter(fn (BillOccurrence $occurrence) => $occurrence->bill !== null)
            ->map(function (BillOccurrence $occurrence) use ($currencyConverter, $request, $selectedCurrency, $today, $vaultArmed): array {
                $bill = $occurrence->bill;
                $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;

                return [
                    'occurrence_id' => $occurrence->id,
                    'bill_id' => $bill->id,
                    'title' => $bill->title,
                    'amount' => $vaultArmed ? $bill->amount : (float) $bill->amount,
                    'currency' => $billCurrency->value,
                    'display_amount' => $vaultArmed
                        ? null
                        : $currencyConverter->format($bill->amount, $billCurrency, $selectedCurrency),
                    'display_currency' => $selectedCurrency->value,
                    'category_name' => $bill->category
                        ? (new CategoryResource($bill->category))->resolve($request)['name']
                        : null,
                    'due_date' => $occurrence->due_date->toDateString(),
                    'is_overdue' => $occurrence->due_date->lt($today),
                    'is_due_today' => $occurrence->due_date->isSameDay($today),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The last three calendar months (calendar-aware) as label + date window.
     *
     * @return array<int, array{label: string, from: Carbon, to: Carbon}>
     */
    private function trendMonths(Request $request): array
    {
        $calendar = FrontendLocalization::normalizeCalendar($request->user()->calendar);
        $now = Carbon::now();
        $months = [];

        foreach ([2, 1, 0] as $offset) {
            if ($calendar === 'jalali') {
                $jalaliNow = Jalalian::fromCarbon($now);
                $month = $jalaliNow->getMonth() - $offset;
                $year = $jalaliNow->getYear();

                while ($month < 1) {
                    $month += 12;
                    $year--;
                }

                $jalaliMonth = new Jalalian($year, $month, 1);
                $daysInMonth = (int) $jalaliMonth->format('t');

                $months[] = [
                    'label' => $jalaliMonth->format('F'),
                    'from' => Carbon::instance($jalaliMonth->toCarbon())->startOfDay(),
                    'to' => Carbon::instance(
                        (new Jalalian($year, $month, $daysInMonth))->toCarbon()
                    )->endOfDay(),
                ];
            } else {
                $monthStart = $now->copy()->subMonthsNoOverflow($offset)->startOfMonth();

                $months[] = [
                    'label' => $monthStart->translatedFormat('F'),
                    'from' => $monthStart->copy()->startOfDay(),
                    'to' => $monthStart->copy()->endOfMonth()->endOfDay(),
                ];
            }
        }

        return $months;
    }

    /**
     * The transactions behind the trend chart.
     *
     * `user_id` is in the select on purpose: the encryption cast resolves the
     * owning user from it, and a partial select without it leaves every amount
     * undecryptable — which silently summed to a flat zero line.
     *
     * @param  array<int, array{label: string, from: Carbon, to: Carbon}>  $months
     * @return SupportCollection<int, Transaction>
     */
    private function trendTransactions(Request $request, array $months): SupportCollection
    {
        return $request->user()->transactions()
            ->whereDate('occurred_at', '>=', $months[0]['from']->toDateString())
            ->whereDate('occurred_at', '<=', $months[2]['to']->toDateString())
            ->get(['id', 'user_id', 'type', 'amount', 'currency', 'occurred_at'])
            ->toBase();
    }

    /**
     * Income and cost totals per month, in the selected currency.
     *
     * Under the vault the totals come back null and the raw rows travel instead —
     * the browser buckets and sums them from the decrypted amounts.
     *
     * @param  array<int, array{label: string, from: Carbon, to: Carbon}>  $months
     * @param  SupportCollection<int, Transaction>  $transactions
     * @return array<int, array{label: string, from: string, to: string, income: float|null, cost: float|null}>
     */
    private function monthlyTrend(
        array $months,
        SupportCollection $transactions,
        CurrencyConverter $currencyConverter,
        Currency $selectedCurrency,
        bool $vaultArmed,
    ): array {
        return collect($months)
            ->map(function (array $month) use ($transactions, $currencyConverter, $selectedCurrency, $vaultArmed): array {
                $window = [
                    'label' => $month['label'],
                    'from' => $month['from']->toDateString(),
                    'to' => $month['to']->toDateString(),
                ];

                if ($vaultArmed) {
                    return [...$window, 'income' => null, 'cost' => null];
                }

                $inMonth = $transactions->filter(fn (Transaction $transaction) => Carbon::parse($transaction->occurred_at)
                    ->between($month['from'], $month['to']));

                $sumFor = fn (TransactionType $type): float => round(
                    $inMonth
                        ->where('type', $type)
                        ->sum(fn (Transaction $transaction) => $currencyConverter->convert(
                            $transaction->amount,
                            $transaction->currency,
                            $selectedCurrency,
                        )),
                    2,
                );

                return [
                    ...$window,
                    'income' => $sumFor(TransactionType::Income),
                    'cost' => $sumFor(TransactionType::Cost),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Live prices for the default (seeded) assets, shown as a ticker.
     *
     * @return array<int, array<string, mixed>>
     */
    private function headlinePrices(
        AssetPriceService $priceService,
        BuildPortfolioBreakdown $breakdownBuilder,
        Currency $selectedCurrency,
    ): array {
        $fmt = $breakdownBuilder->formatter($selectedCurrency);

        return InvestmentAsset::query()
            ->whereNull('user_id')
            ->orderBy('id')
            ->get()
            ->map(fn (InvestmentAsset $asset): array => [
                'key' => $asset->slug,
                'label' => $asset->label(),
                'icon' => $asset->icon,
                'icon_svg' => $asset->icon_svg,
                'color' => $asset->color,
                'unit' => $asset->unit,
                'price_formatted' => $fmt($priceService->priceFor($asset)),
                'available' => $priceService->priceAvailableFor($asset),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{month: string, year: int, dayOfMonth: int, daysInMonth: int, progress: int}
     */
    private function currentPeriod(string $calendar): array
    {
        $now = Carbon::now();

        if ($calendar === 'jalali') {
            $jalali = Jalalian::fromCarbon($now);
            $dayOfMonth = (int) $jalali->format('j');
            $daysInMonth = (int) $jalali->format('t');

            return [
                'month' => $jalali->format('F'),
                'year' => (int) $jalali->format('Y'),
                'dayOfMonth' => $dayOfMonth,
                'daysInMonth' => $daysInMonth,
                'progress' => (int) round(($dayOfMonth / $daysInMonth) * 100),
            ];
        }

        $dayOfMonth = (int) $now->format('j');
        $daysInMonth = (int) $now->format('t');

        return [
            'month' => $now->translatedFormat('F'),
            'year' => (int) $now->format('Y'),
            'dayOfMonth' => $dayOfMonth,
            'daysInMonth' => $daysInMonth,
            'progress' => (int) round(($dayOfMonth / $daysInMonth) * 100),
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function currentMonthRange(string $calendar): array
    {
        $now = Carbon::now();

        if ($calendar === 'jalali') {
            $jalali = Jalalian::fromCarbon($now);
            // Jalalian::toCarbon() returns \Carbon\Carbon (not \Illuminate\Support\Carbon),
            // so we wrap with Carbon::instance() to satisfy instanceof checks downstream.
            $from = Carbon::instance(
                (new Jalalian($jalali->getYear(), $jalali->getMonth(), 1))->toCarbon()
            )->startOfDay();

            $daysInMonth = (int) $jalali->format('t');
            $to = Carbon::instance(
                (new Jalalian($jalali->getYear(), $jalali->getMonth(), $daysInMonth))->toCarbon()
            )->endOfDay();
        } else {
            $from = $now->copy()->startOfMonth()->startOfDay();
            $to = $now->copy()->endOfMonth()->endOfDay();
        }

        return [$from, $to];
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request, SaveTransaction $saveTransaction): RedirectResponse
    {
        $saveTransaction->handle($request->user(), $request->transactionData());

        return redirect()->to($request->headers->get('referer') ?: route('dashboard'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        SaveTransaction $saveTransaction,
    ): RedirectResponse {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $saveTransaction->handle($request->user(), $request->transactionData(), $transaction);

        return redirect()->to($request->headers->get('referer') ?: route('dashboard'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $transaction->delete();

        return redirect()->to($request->headers->get('referer') ?: route('dashboard'));
    }

    /**
     * Bulk-delete transactions belonging to the authenticated user.
     */
    public function destroyBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $request->user()->transactions()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return back();
    }

    /**
     * Bulk-reassign a category on transactions of a single type.
     */
    public function updateBulkCategory(Request $request): RedirectResponse
    {
        $typeValues = array_map(fn (TransactionType $t) => $t->value, TransactionType::cases());

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'type' => ['required', 'string', Rule::in($typeValues)],
            'category_id' => ['nullable', 'integer'],
        ]);

        $categoryId = $validated['category_id'] ?? null;

        if ($categoryId !== null) {
            $type = TransactionType::tryFrom($validated['type']);
            $exists = Category::query()
                ->availableFor($request->user())
                ->when($type !== null, fn (Builder $q) => $q->where('type', $type))
                ->whereKey($categoryId)
                ->exists();

            if (! $exists) {
                abort(422, 'Invalid category');
            }
        }

        $request->user()->transactions()
            ->whereIn('id', $validated['ids'])
            ->where('type', $validated['type'])
            ->update(['category_id' => $categoryId]);

        return back();
    }
}
