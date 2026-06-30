<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bills can legitimately have no category (the "No category" option in both
 * the web form and the Telegram bot), but MarkBillOccurrencePaid creates a
 * transaction with that same (possibly null) category_id when a bill is paid.
 * The column was NOT NULL, so paying a category-less bill crashed with a DB
 * constraint violation — this makes it nullable to match.
 *
 * doctrine/dbal isn't installed, so MySQL uses a direct MODIFY and SQLite
 * (which can't ALTER COLUMN) uses the add/copy/drop/rename pattern instead
 * of Schema::table()->change().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('category_id_nullable')->nullable()->after('category_id')
                    ->constrained('categories')->restrictOnDelete();
            });

            DB::statement('UPDATE transactions SET category_id_nullable = category_id');

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('category_id_nullable', 'category_id');
            });

            return;
        }

        DB::statement('ALTER TABLE transactions MODIFY category_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // Backfill any nulls before re-adding NOT NULL so the down-migration
        // itself doesn't fail on data created while the column was nullable.
        $fallbackCategoryId = DB::table('categories')->whereNull('user_id')->value('id');

        if ($fallbackCategoryId !== null) {
            DB::table('transactions')->whereNull('category_id')->update(['category_id' => $fallbackCategoryId]);
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('category_id_not_null')->after('category_id')
                    ->constrained('categories')->restrictOnDelete();
            });

            DB::statement('UPDATE transactions SET category_id_not_null = category_id');

            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('category_id_not_null', 'category_id');
            });

            return;
        }

        DB::statement('ALTER TABLE transactions MODIFY category_id BIGINT UNSIGNED NOT NULL');
    }
};
