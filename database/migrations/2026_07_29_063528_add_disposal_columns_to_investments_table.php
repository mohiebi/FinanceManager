<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an investment row record a disposal as well as an acquisition.
 *
 * A sale is a second entry with a negative quantity, never an edit or a delete of
 * the original: only that shape can express a partial sale, and it keeps the
 * purchase history intact. Carrying the average cost basis on the disposal makes
 * the remaining per-unit basis fall out of the existing sums unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Deliberately NOT encrypted. `quantity` is, so with the vault armed the
            // server cannot see the sign and has no way to tell a purchase from a
            // sale — it could not validate, filter or aggregate without this. It
            // leaks no more than `occurred_at` and `currency` already do.
            $table->string('kind', 8)->default('buy')->after('asset_type');

            // Per-unit proceeds, encrypted like every other money column here.
            $table->text('sale_price')->nullable()->after('cost_basis_currency');
            $table->string('sale_price_currency', 10)->nullable()->after('sale_price');

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'kind']);
            $table->dropColumn(['kind', 'sale_price', 'sale_price_currency']);
        });
    }
};
