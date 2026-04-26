<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\SaveTransaction;
use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $transactions = $user->transactions()
            ->with('category:id,name,type')
            ->latest('occurred_at')
            ->latest()
            ->get();

        $categories = Category::query()
            ->availableFor($user)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard', [
            'transactions' => [
                'costs' => $transactions
                    ->where('type', TransactionType::Cost)
                    ->values()
                    ->map(fn (Transaction $transaction) => (new TransactionResource($transaction))->resolve($request)),
                'incomes' => $transactions
                    ->where('type', TransactionType::Income)
                    ->values()
                    ->map(fn (Transaction $transaction) => (new TransactionResource($transaction))->resolve($request)),
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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request, SaveTransaction $saveTransaction): RedirectResponse
    {
        $saveTransaction->handle($request->user(), $request->transactionData());

        return to_route('dashboard');
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

        return to_route('dashboard');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $transaction->delete();

        return to_route('dashboard');
    }
}
