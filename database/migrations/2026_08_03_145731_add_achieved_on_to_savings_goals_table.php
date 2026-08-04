<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day a goal was first observed to have met its target.
 *
 * Recorded rather than derived because `reached` is a fact about holdings, which
 * change: sell the gold and a met goal would silently stop being met, taking its
 * place in the achieved list with it. A stamp is also the only way to ask "what
 * did I finish recently", which is what the portfolio shows.
 *
 * Plaintext on purpose, and deliberately not a quantity. Under the vault the
 * server cannot tell whether a goal is reached, but it can still filter on a
 * date — so the portfolio's three-month window works identically for everyone.
 * The date leaks nothing the target date did not already.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->date('achieved_on')->nullable()->after('started_on');

            $table->index(['user_id', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::table('savings_goals', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'achieved_on']);
            $table->dropColumn('achieved_on');
        });
    }
};
