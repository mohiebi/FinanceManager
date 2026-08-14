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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Null for a coupon that covered the whole price: there was nothing
            // to pay, so no intent was ever opened.
            $table->foreignUlid('subscription_payment_id')->nullable()
                ->constrained()->nullOnDelete();

            // Set once the months are actually on the account.
            $table->foreignUlid('subscription_grant_id')->nullable()
                ->constrained()->nullOnDelete();

            // A CouponRedemptionStatus value. Reserved rows are what make a
            // redemption limit mean anything — see the enum for why the claim
            // happens when the intent opens rather than when the money lands.
            $table->string('status', 20);

            // What this redemption actually took off, snapshotted. A coupon
            // edited or disabled later must not change what a past buyer got.
            $table->string('discount_usd', 20);

            $table->timestamps();

            // One coupon per payment, enforced by the database rather than by
            // an application convention that a retry could step around.
            $table->unique('subscription_payment_id');

            $table->index(['coupon_id', 'status']);
            $table->index(['coupon_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
