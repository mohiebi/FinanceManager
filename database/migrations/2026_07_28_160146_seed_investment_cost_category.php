<?php

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves the default `Investment` category from income to cost.
 *
 * Buying an asset is money leaving, and the report's "exclude investments" filter
 * needs a cost category to match on. The income side is dropped: money coming back
 * out of an asset is either a sale — portfolio profit and loss, already tracked
 * with cost basis by the Investments module — or a recurring payout, which is just
 * income under whatever it actually is. One slug meaning both was ambiguous.
 *
 * Both halves live in one migration because they are one decision; the seeder
 * matches, but existing installs never re-run seeders.
 */
return new class extends Migration
{
    private const SLUG = 'investment';

    public function up(): void
    {
        $this->addCostCategory();
        $this->dropIncomeCategory();
    }

    private function addCostCategory(): void
    {
        $exists = DB::table('categories')
            ->whereNull('user_id')
            ->where('type', TransactionType::Cost->value)
            ->where('slug', self::SLUG)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('categories')->insert([
            'user_id' => null,
            'type' => TransactionType::Cost->value,
            'slug' => self::SLUG,
            'name' => 'Investment',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Retire the seeded income category, moving anything filed under it to Other.
     *
     * Only the seeded default: a category a user made themselves is their call,
     * not ours. `transactions.category_id` is restrictOnDelete, so the re-point has
     * to happen first — otherwise this migration would abort on the one thing it
     * most needs to handle, an install where someone actually used the category.
     */
    private function dropIncomeCategory(): void
    {
        $category = $this->defaultIncomeCategory(self::SLUG);
        $fallback = $this->defaultIncomeCategory('other');

        if ($category === null) {
            return;
        }

        $inUse = DB::table('transactions')->where('category_id', $category->id)->exists();

        if ($inUse) {
            // Nowhere safe to move them: leave the category alone rather than
            // delete a user's income rows or crash the deploy.
            if ($fallback === null) {
                return;
            }

            DB::table('transactions')
                ->where('category_id', $category->id)
                ->update(['category_id' => $fallback->id]);
        }

        DB::table('categories')->where('id', $category->id)->delete();
    }

    private function defaultIncomeCategory(string $slug): ?object
    {
        return DB::table('categories')
            ->whereNull('user_id')
            ->where('type', TransactionType::Income->value)
            ->where('slug', $slug)
            ->first();
    }

    public function down(): void
    {
        $this->restoreIncomeCategory();
        $this->removeCostCategory();
    }

    private function restoreIncomeCategory(): void
    {
        if ($this->defaultIncomeCategory(self::SLUG) !== null) {
            return;
        }

        // The rows moved to Other on the way up are not moved back: by then they
        // are indistinguishable from anything else filed under Other.
        DB::table('categories')->insert([
            'user_id' => null,
            'type' => TransactionType::Income->value,
            'slug' => self::SLUG,
            'name' => 'Investment',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function removeCostCategory(): void
    {
        // Only removed while nothing points at it. Dropping a category that has
        // rows against it would take the user's categorisation with it, which is a
        // far worse outcome than leaving one spare row behind.
        $category = DB::table('categories')
            ->whereNull('user_id')
            ->where('type', TransactionType::Cost->value)
            ->where('slug', self::SLUG)
            ->first();

        if ($category === null) {
            return;
        }

        $inUse = DB::table('transactions')->where('category_id', $category->id)->exists()
            || DB::table('bills')->where('category_id', $category->id)->exists();

        if ($inUse) {
            return;
        }

        DB::table('categories')->where('id', $category->id)->delete();
    }
};
