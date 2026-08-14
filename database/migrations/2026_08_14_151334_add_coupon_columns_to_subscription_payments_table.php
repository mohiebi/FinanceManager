<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('price_usd')
                ->constrained()->nullOnDelete();

            // The undiscounted price, kept only so the buyer and the operator
            // can see what was taken off.
            //
            // price_usd deliberately keeps holding what is actually owed: it is
            // what AssetQuoteService::priceIn() converts, which is what the
            // expected on-chain amount and its nonce are derived from. Storing
            // the list price there instead would leave the snapshot and the
            // chain disagreeing, and every discounted payment would fail
            // verification on an amount mismatch.
            $table->string('list_price_usd', 20)->nullable()->after('coupon_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'list_price_usd']);
        });
    }
};
