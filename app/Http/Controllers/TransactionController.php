<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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
                    ->map(fn (Transaction $transaction) => $this->transactionPayload($transaction)),
                'incomes' => $transactions
                    ->where('type', TransactionType::Income)
                    ->values()
                    ->map(fn (Transaction $transaction) => $this->transactionPayload($transaction)),
            ],
            'categories' => [
                'cost' => $categories
                    ->where('type', TransactionType::Cost)
                    ->values()
                    ->map(fn (Category $category) => $this->categoryPayload($category)),
                'income' => $categories
                    ->where('type', TransactionType::Income)
                    ->values()
                    ->map(fn (Category $category) => $this->categoryPayload($category)),
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
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTransaction($request);

        $request->user()->transactions()->create($validated);

        return to_route('dashboard');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $transaction->update($this->validateTransaction($request));

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

    /**
     * @return array<string, mixed>
     */
    private function validateTransaction(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(TransactionType::class)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
        ]);

        $category = Category::query()->find($validated['category_id']);
        $transactionType = TransactionType::from($validated['type']);

        validator($validated)->after(function (Validator $validator) use ($category, $request, $transactionType): void {
            if (! $category instanceof Category) {
                return;
            }

            if ($category->type !== $transactionType) {
                $validator->errors()->add('category_id', 'Choose a category for the selected transaction type.');
            }

            if ($category->user_id !== null && (int) $category->user_id !== (int) $request->user()->id) {
                $validator->errors()->add('category_id', 'Choose one of your categories or a default category.');
            }
        })->validate();

        $validated['description'] = $validated['description'] ?? null;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency->value,
            'title' => $transaction->title,
            'description' => $transaction->description,
            'occurred_at' => $transaction->occurred_at->toDateString(),
            'category' => $transaction->category ? $this->categoryPayload($transaction->category) : null,
            'category_id' => $transaction->category_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'type' => $category->type->value,
            'is_default' => $category->is_default,
        ];
    }
}
