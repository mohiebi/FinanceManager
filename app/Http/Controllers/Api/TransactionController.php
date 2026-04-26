<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transactions\SaveTransaction;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = $request->user()
            ->transactions()
            ->with('category:id,name,type')
            ->when(
                TransactionType::tryFrom((string) $request->query('type')),
                fn ($query, TransactionType $type) => $query->where('type', $type->value),
            )
            ->latest('occurred_at')
            ->latest()
            ->get();

        return TransactionResource::collection($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request, SaveTransaction $saveTransaction): TransactionResource
    {
        $transaction = $saveTransaction->handle($request->user(), $request->transactionData());

        return (new TransactionResource($transaction->load('category:id,name,type')))
            ->additional(['message' => 'Transaction created.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        return new TransactionResource($transaction->load('category:id,name,type'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        SaveTransaction $saveTransaction,
    ): TransactionResource {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $transaction = $saveTransaction->handle($request->user(), $request->transactionData(), $transaction);

        return (new TransactionResource($transaction->load('category:id,name,type')))
            ->additional(['message' => 'Transaction updated.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        abort_unless((int) $transaction->user_id === (int) $request->user()->id, 404);

        $transaction->delete();

        return response()->json(status: 204);
    }
}
