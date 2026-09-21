<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CalendarDates;
use App\Support\TransactionListing;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the user\'s transactions, newest first. Supports filtering by date range (Gregorian or Jalali dates, auto-detected), type (cost or income), category, and free-text search over title and description. Results are paginated.')]
class ListTransactionsTool extends Tool
{
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
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = $user->transactions()
            ->with('category.parent:id,name')
            ->when($validated['from_date'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($validated['to_date'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            // A parent stands for its whole family, as the app's own filter does.
            ->when($validated['category_id'] ?? null, fn ($query, $categoryId) => $query->whereIn(
                'category_id',
                Category::familiesFor($user, [(int) $categoryId])[(int) $categoryId],
            ))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $perPage = (int) ($validated['per_page'] ?? 25);
        $page = (int) ($validated['page'] ?? 1);
        $search = trim((string) ($validated['search'] ?? ''));

        // Title and description are encrypted at rest, so searching has to happen
        // after decryption — which means materialising the filtered range first.
        // Without a search term the database can still do the paging.
        $paginator = $search === ''
            ? $query->paginate(perPage: $perPage, page: $page)
            : TransactionListing::paginate(
                TransactionListing::search($query->get(), $search),
                $page,
                $perPage,
            );

        $isJalali = CalendarDates::isJalaliUser($user);

        return Response::structured([
            'calendar' => $isJalali ? 'jalali' : 'gregorian',
            'transactions' => collect($paginator->items())->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'title' => $transaction->title,
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency->value,
                'type' => $transaction->type->value,
                'category_id' => $transaction->category_id,
                'category' => $transaction->category?->name,
                'category_path' => $transaction->category?->pathName(),
                'occurred_at' => $transaction->occurred_at->toDateString(),
                'occurred_at_jalali' => $isJalali
                    ? CalendarDates::toJalali($transaction->occurred_at)
                    : null,
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
            'from_date' => $schema->string()->description('Only include transactions on or after this date (YYYY-MM-DD). Gregorian or Jalali — Jalali years (1100-1599) are auto-detected and converted server-side; pass the user\'s Jalali dates unchanged.'),
            'to_date' => $schema->string()->description('Only include transactions on or before this date (YYYY-MM-DD). Gregorian or Jalali, auto-detected.'),
            'type' => $schema->string()->enum(['cost', 'income'])->description('Filter by transaction type.'),
            'category_id' => $schema->integer()->description('Filter by category id (see list-categories). A parent category includes its subcategories.'),
            'search' => $schema->string()->description('Free-text search over title and description.'),
            'page' => $schema->integer()->description('Page number, starting at 1.'),
            'per_page' => $schema->integer()->description('Results per page (max 50, default 25).'),
        ];
    }
}
