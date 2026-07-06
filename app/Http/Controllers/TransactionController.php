<?php

namespace App\Http\Controllers;

use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Actions\Transactions\CurrencyConverter;
use App\Actions\Transactions\SaveTransaction;
use App\Enums\Currency;
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
use App\Support\FrontendLocalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    ): Response {
        $user = $request->user();
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;

        return $this->renderTransactionWorkspace($request, $currencyConverter, 'Dashboard', false, [
            'upcomingBills' => $this->upcomingBills($request, $currencyConverter, $selectedCurrency),
            'monthlyTrend' => $this->monthlyTrend($request, $currencyConverter, $selectedCurrency),
            // Deferred: both hit the price cache (and possibly tgju on a cold
            // cache), so they load in a background request after first paint —
            // same pattern as the Portfolio page.
            'portfolio' => Inertia::defer(fn () => $breakdownBuilder->snapshot($user, $selectedCurrency)),
            'assetPrices' => Inertia::defer(fn () => $this->headlinePrices($priceService, $breakdownBuilder, $selectedCurrency)),
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ]);
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
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;
        $selectedType = $withFilters
            ? $this->resolveTransactionType((string) $request->query('type'))
            : null;
        $selectedCategoryId = $withFilters
            ? $this->resolveCategoryId($request)
            : null;
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

        $transactions = $user->transactions()
            ->with('category:id,name,type')
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
            ->when(
                $fromDate instanceof Carbon,
                fn (Builder $query) => $query->whereDate('occurred_at', '>=', $fromDate->toDateString()),
            )
            ->when(
                $toDate instanceof Carbon,
                fn (Builder $query) => $query->whereDate('occurred_at', '<=', $toDate->toDateString()),
            )
            ->latest('occurred_at')
            ->latest()
            ->get();

        $categories = Category::query()
            ->availableFor($user)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $costs = $transactions
            ->where('type', TransactionType::Cost)
            ->values();

        $incomes = $transactions
            ->where('type', TransactionType::Income)
            ->values();

        return Inertia::render($component, [
            'filters' => [
                'search' => $search,
                'type' => $selectedType?->value ?? 'all',
                'category' => $selectedCategoryId,
                'from' => $fromDate?->toDateString() ?? '',
                'to' => $toDate?->toDateString() ?? '',
            ],
            'transactions' => [
                'costs' => $costs
                    ->map(fn (Transaction $transaction) => [
                        ...(new TransactionResource($transaction))->resolve($request),
                        'display_amount' => $currencyConverter->format(
                            $transaction->amount,
                            $transaction->currency,
                            $selectedCurrency,
                        ),
                        'display_currency' => $selectedCurrency->value,
                    ]),
                'incomes' => $incomes
                    ->map(fn (Transaction $transaction) => [
                        ...(new TransactionResource($transaction))->resolve($request),
                        'display_amount' => $currencyConverter->format(
                            $transaction->amount,
                            $transaction->currency,
                            $selectedCurrency,
                        ),
                        'display_currency' => $selectedCurrency->value,
                    ]),
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
            'period' => $this->currentPeriod($calendar),
            ...$extraProps,
        ]);
    }

    /**
     * The next few unpaid bill occurrences, soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingBills(Request $request, CurrencyConverter $currencyConverter, Currency $selectedCurrency): array
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
            ->map(function (BillOccurrence $occurrence) use ($currencyConverter, $selectedCurrency, $today): array {
                $bill = $occurrence->bill;
                $billCurrency = Currency::tryFrom((string) $bill->currency) ?? $selectedCurrency;

                return [
                    'occurrence_id' => $occurrence->id,
                    'bill_id' => $bill->id,
                    'title' => $bill->title,
                    'display_amount' => $currencyConverter->format($bill->amount, $billCurrency, $selectedCurrency),
                    'display_currency' => $selectedCurrency->value,
                    'category_name' => $bill->category?->name,
                    'due_date' => $occurrence->due_date->toDateString(),
                    'is_overdue' => $occurrence->due_date->lt($today),
                    'is_due_today' => $occurrence->due_date->isSameDay($today),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Income and cost totals for the last three calendar months (calendar-aware),
     * in the selected currency. The workspace itself only loads the current
     * month's transactions, so the trend needs its own aggregate.
     *
     * @return array<int, array{label: string, income: float, cost: float}>
     */
    private function monthlyTrend(Request $request, CurrencyConverter $currencyConverter, Currency $selectedCurrency): array
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

        $transactions = $request->user()->transactions()
            ->whereDate('occurred_at', '>=', $months[0]['from']->toDateString())
            ->whereDate('occurred_at', '<=', $months[2]['to']->toDateString())
            ->get(['type', 'amount', 'currency', 'occurred_at']);

        return collect($months)
            ->map(function (array $month) use ($transactions, $currencyConverter, $selectedCurrency): array {
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
                    'label' => $month['label'],
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
}
