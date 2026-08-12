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
        Schema::create('subscription_payments', function (Blueprint $table) {
            // ULID rather than an auto-increment: payment ids get quoted in
            // support email, and a sequential one would be both guessable and a
            // public running total of how many subscriptions have been sold.
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // A PaymentStatus value.
            $table->string('status', 20);

            // ── What was bought ────────────────────────────────────────────
            // Snapshots, all of them. Repricing a plan, rotating the receiving
            // address or moving the market must never change the terms of a
            // payment already in flight.
            $table->string('plan', 20);
            $table->unsignedTinyInteger('months');

            // Every money column here is a string, not a decimal, and that is
            // load-bearing rather than lazy. A DECIMAL comes back from SQLite as
            // a PHP float, and the low-order digits a float cannot hold are
            // precisely where this table keeps its per-payment nonce. Casting
            // such a float back to string also yields scientific notation for
            // small amounts, which is not a number any of this can parse.
            // Strings round-trip byte for byte on every driver.
            //
            // Nothing compares these in SQL — all arithmetic goes through
            // App\Support\Billing\TokenAmount — so no ordering is given up.
            $table->string('price_usd', 20);

            // ── How it settles ─────────────────────────────────────────────
            // Null network means an administrator created the row by hand, so no
            // chain was ever involved and none of the chain columns apply.
            $table->string('network', 20)->nullable();
            $table->unsignedBigInteger('chain_id')->nullable();
            $table->string('asset', 12);
            // Null contract means the chain's native currency.
            $table->string('token_contract', 80)->nullable();
            $table->unsignedTinyInteger('asset_decimals');
            $table->string('pay_to_address', 80)->nullable();

            // USD per unit of the asset at the moment the intent opened. Exactly
            // 1 for a stablecoin, which never needs quoting.
            $table->string('quote_rate', 32);
            $table->timestamp('quote_expires_at')->nullable();

            // The exact amount this payment expects, carrying a per-payment nonce
            // in its lowest digits so that no two open intents ever expect the
            // same figure. Matched exactly at verification: any tolerance band
            // wide enough to be useful would be wide enough to span a
            // neighbouring intent's amount, which is the whole thing the nonce
            // exists to prevent.
            $table->string('expected_amount', 40);

            // ── What the chain actually said ───────────────────────────────
            $table->string('tx_hash', 80)->nullable();
            $table->string('from_address', 80)->nullable();
            // Wide enough for a full uint256 rendered at 18 decimal places — a
            // hostile contract can emit one, and it has to be storable to be
            // reportable.
            $table->string('received_amount', 100)->nullable();
            $table->unsignedInteger('confirmations')->nullable();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamp('block_timestamp')->nullable();

            // ── Lifecycle ──────────────────────────────────────────────────
            $table->unsignedSmallInteger('attempts')->default(0);
            // A PaymentFailureReason value. Set on a retryable reason too, so the
            // buyer can be told we are having trouble reaching the network rather
            // than being left staring at a spinner.
            $table->string('failure_reason', 40)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');

            // ── Manual settlement ──────────────────────────────────────────
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();

            $table->timestamps();

            // Replay protection, and the anti-squatting primitive: claiming a
            // hash IS this insert, so two payments racing for one transaction are
            // resolved by the database rather than by a check that can interleave.
            // Open intents hold a null hash, which every supported engine allows
            // any number of.
            $table->unique(['network', 'tx_hash']);

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Deferred from the grants migration, which necessarily ran first.
        Schema::table('subscription_grants', function (Blueprint $table) {
            $table->foreign('subscription_payment_id')
                ->references('id')
                ->on('subscription_payments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_grants', function (Blueprint $table) {
            $table->dropForeign(['subscription_payment_id']);
        });

        Schema::dropIfExists('subscription_payments');
    }
};
