<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionListing;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Guards the agreement between searching and paging.
 *
 * Both used to happen in SQL, so they were trivially consistent. Now that search
 * runs in PHP over decrypted values, a paginator built from the query builder
 * would page an unfiltered result set — right count, wrong rows. These tests fail
 * the moment anyone reintroduces builder-based paging.
 */
function seedSearchableTransactions(User $user, int $matching, int $other): void
{
    $today = Carbon::today()->toDateString();

    Transaction::factory()->cost()->count($matching)->create([
        'user_id' => $user->id,
        'title' => 'Coffee run',
        'occurred_at' => $today,
    ]);

    Transaction::factory()->cost()->count($other)->create([
        'user_id' => $user->id,
        'title' => 'Rent payment',
        'occurred_at' => $today,
    ]);
}

test('the transactions page pages a searched result set, not the unfiltered one', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 18, other: 7);

    $this->actingAs($user)
        ->get(route('transactions.index', ['search' => 'coffee', 'cost_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.costs.total', 18)
            ->where('transactions.meta.costs.last_page', 2)
            ->where('transactions.meta.costs.current_page', 2)
            // 18 matches at 15 a page leaves 3 on page two.
            ->has('transactions.costs', 3)
        );
});

test('a searched page never leaks a non-matching row', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 18, other: 7);

    $response = $this->actingAs($user)
        ->get(route('transactions.index', ['search' => 'coffee', 'cost_page' => 2]))
        ->assertOk();

    $titles = collect($response->viewData('page')['props']['transactions']['costs'])
        ->pluck('title');

    expect($titles)->each->toBe('Coffee run');
});

test('the report page pages a searched result set too', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 18, other: 7);

    $this->actingAs($user)
        ->get(route('report', ['search' => 'coffee', 'cost_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.costs.total', 18)
            ->where('transactions.meta.costs.last_page', 2)
            ->has('transactions.costs', 3)
            // Analytics stay unpaginated — the charts need the whole set.
            ->has('analyticsTransactions.costs', 18)
            ->where('summary.count', 18)
        );
});

test('search matches the description as well as the title', function () {
    $user = User::factory()->create();

    Transaction::factory()->cost()->create([
        'user_id' => $user->id,
        'title' => 'Unrelated',
        'description' => 'paid for parking',
        'occurred_at' => Carbon::today()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['search' => 'parking']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('transactions.costs', 1));
});

test('search ignores case', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 2, other: 3);

    $this->actingAs($user)
        ->get(route('transactions.index', ['search' => 'COFFEE']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transactions.meta.costs.total', 2));
});

test('the page size follows the per_page the user picked', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 18, other: 7);

    $this->actingAs($user)
        ->get(route('transactions.index', ['per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.per_page', 10)
            ->where('filters.per_page', 10)
            // 25 rows at 10 a page.
            ->where('transactions.meta.costs.last_page', 3)
            ->has('transactions.costs', 10)
        );
});

test('a per_page off the offered list falls back to the default', function () {
    $user = User::factory()->create();
    seedSearchableTransactions($user, matching: 18, other: 7);

    // 5000 would page the whole table into one response, and a bulk action on
    // that many rows would be refused by the 200-id cap anyway.
    $this->actingAs($user)
        ->get(route('transactions.index', ['per_page' => 5000]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.meta.per_page', TransactionListing::PER_PAGE)
            ->has('transactions.costs', TransactionListing::PER_PAGE)
        );
});

test('the offered page sizes stay within the bulk action id cap', function () {
    // Both tables can be select-all'd at once, so the largest page size has to
    // leave that under the 200 ids destroyBulk/updateBulkCategory validate.
    expect(max(TransactionListing::PER_PAGE_OPTIONS) * 2)->toBeLessThanOrEqual(200)
        ->and(TransactionListing::PER_PAGE_OPTIONS)->toContain(TransactionListing::PER_PAGE);
});

test('the transactions page offers the page sizes it accepts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('perPageOptions', TransactionListing::PER_PAGE_OPTIONS)
        );
});

test('a page past the end reports the true last page and no rows', function () {
    $items = collect(range(1, 20));

    $paginator = TransactionListing::paginate($items, page: 9);

    // Matches how a database paginator behaves for an out-of-range page.
    expect($paginator->items())->toBe([])
        ->and($paginator->lastPage())->toBe(2)
        ->and($paginator->total())->toBe(20)
        ->and($paginator->currentPage())->toBe(9);
});

test('an empty collection still reports one page', function () {
    $paginator = TransactionListing::paginate(collect(), page: 1);

    expect($paginator->lastPage())->toBe(1)
        ->and($paginator->total())->toBe(0)
        ->and($paginator->items())->toBe([]);
});

test('category=none filters to uncategorised transactions', function () {
    $user = User::factory()->create();
    $category = Category::factory()->cost()->forUser($user)->create();
    $today = Carbon::today()->toDateString();

    Transaction::factory()->cost()->for($user)->create([
        'occurred_at' => $today,
        'category_id' => null,
    ]);
    Transaction::factory()->cost()->for($user)->for($category)->create([
        'occurred_at' => $today,
    ]);

    // The logbook's uncategorised count links here; without the sentinel the
    // number would be a dead end.
    $this->actingAs($user)
        ->get(route('transactions.index', ['category' => 'none']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.category', 'none')
            ->has('transactions.costs', 1)
            ->etc());
});
