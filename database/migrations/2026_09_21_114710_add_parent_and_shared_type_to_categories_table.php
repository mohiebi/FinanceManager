<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            // One level only: a parent is always top-level, which the request
            // rules enforce. Deleting a parent promotes its children rather
            // than taking them with it.
            $parent = $table->foreignId('parent_id')->nullable()->after('user_id');

            if (! $this->rebuildsToAddForeignKeys()) {
                $parent->constrained('categories')->nullOnDelete();
            }

            // `type` stays the category's home type, so the per-type unique
            // indexes keep meaning what they did; this only widens where the
            // category may be used.
            $table->boolean('for_both_types')->default(false)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if ($this->rebuildsToAddForeignKeys()) {
                $table->dropColumn('parent_id');
            } else {
                $table->dropConstrainedForeignId('parent_id');
            }

            $table->dropColumn('for_both_types');
        });
    }

    /**
     * SQLite can only add a foreign key by rebuilding the table, and the
     * rebuild does damage here: it recreates the defaults-only slug index
     * without its WHERE clause, and it moves `categories` after `transactions`
     * in the schema, so deleting a user cascades into categories first and
     * trips the restrict key on transactions.category_id.
     *
     * The column goes in without the constraint there instead. Nothing is lost
     * that the app relies on: CategoryController::destroy() promotes children
     * itself, and a child always belongs to the same user as any parent that
     * can be deleted.
     */
    private function rebuildsToAddForeignKeys(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }
};
