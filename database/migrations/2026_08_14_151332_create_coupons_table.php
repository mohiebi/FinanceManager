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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // The public identifier. Stored uppercase and compared uppercase, so
            // a buyer typing it in lower case still redeems.
            $table->string('code', 40)->unique();

            // A CouponKind value. Exactly one of the two amount columns below is
            // populated, decided by this.
            $table->string('kind', 20);
            $table->unsignedTinyInteger('percent_off')->nullable();

            // A decimal string, matching price_usd on subscription_payments for
            // the reason that column documents: a DECIMAL comes back from SQLite
            // as a float, and money that has been through a float cannot be
            // trusted to compare.
            $table->string('amount_off_usd', 20)->nullable();

            // Null means anybody may redeem it. Set locks the coupon to one
            // account, which is the difference between a launch code and a
            // credit issued to a particular customer.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            // Null means unlimited on both counts.
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_user')->nullable();

            $table->timestamp('valid_until')->nullable();

            // Preferred over deleting: a redeemed coupon still has to be
            // explicable months later, and deleting it would orphan its ledger.
            $table->timestamp('disabled_at')->nullable();

            $table->text('note')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'disabled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
