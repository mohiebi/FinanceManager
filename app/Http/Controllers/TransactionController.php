<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\CurrencyConverter;
use App\Actions\Transactions\SaveTransaction;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TransactionController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function dashboard(Request $request, CurrencyConverter $currencyConverter): Response
    {
        return $this->renderTransactionWorkspace($request, $currencyConverter, 'Dashboard', false);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, CurrencyConverter $currencyConverter): Response
    {
        return $this->renderTransactionWorkspace($request, $currencyConverter, 'Transactions', true);
    }

    private function renderTransactionWorkspace(
        Request $request,
        CurrencyConverter $currencyConverter,
        string $component,
        bool $withFilters,
    ): Response
    {
        $user = $request->user();
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
                    'label' => strtoupper($currency->value),
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
