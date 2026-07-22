<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s transactions, newest first. Supports filtering by date range (YYYY-MM-DD, Gregorian), type (cost or income), category, and free-text search over title and description. Results are paginated.')]
class ListTransactionsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $user->transactions()
            ->with('category')
            ->when($validated['from_date'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($validated['to_date'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"),
            ))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: (int) ($validated['per_page'] ?? 25),
                page: (int) ($validated['page'] ?? 1),
            );

        return Response::structured([
            'transactions' => collect($paginator->items())->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'title' => $transaction->title,
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency->value,
                'type' => $transaction->type->value,
                'category_id' => $transaction->category_id,
                'category' => $transaction->category?->name,
                'occurred_at' => $transaction->occurred_at->toDateString(),
            ])->all(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from_date' => $schema->string()->description('Only include transactions on or after this date (YYYY-MM-DD, Gregorian).'),
            'to_date' => $schema->string()->description('Only include transactions on or before this date (YYYY-MM-DD, Gregorian).'),
            'type' => $schema->string()->enum(['cost', 'income'])->description('Filter by transaction type.'),
            'category_id' => $schema->integer()->description('Filter by category id (see list-categories).'),
            'search' => $schema->string()->description('Free-text search over title and description.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
            'per_page' => $schema->integer()->description('Results per page (max 50, default 25).'),
        ];
    }
}
