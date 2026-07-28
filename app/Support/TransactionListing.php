<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Free-text search and paging over transactions, done in PHP rather than SQL.
 *
 * `title` and `description` are encrypted at rest, and every write uses a fresh
 * IV — so `WHERE title LIKE '%rent%'` matches nothing at all, and paging a query
 * that can no longer be filtered would return the wrong rows on every page but
 * the first. Both operations therefore have to happen after decryption.
 */
final class TransactionListing
{
    public const PER_PAGE = 15;

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    public static function search(Collection $transactions, string $search): Collection
    {
        if ($search === '') {
            return $transactions;
        }

        $needle = mb_strtolower($search);

        return $transactions
            ->filter(fn (Transaction $transaction): bool => str_contains(mb_strtolower((string) $transaction->title), $needle)
                || str_contains(mb_strtolower((string) $transaction->description), $needle))
            ->values();
    }

    /**
     * Page an already-materialised collection.
     *
     * Returns a real LengthAwarePaginator so callers keep the same
     * currentPage/lastPage/total API they had with a database paginator, including
     * its behaviour for a page past the end: no items, but the true last page.
     *
     * @param  Collection<int, Transaction>  $items
     * @return LengthAwarePaginator<int, Transaction>
     */
    public static function paginate(Collection $items, int $page, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
        );
    }
}
