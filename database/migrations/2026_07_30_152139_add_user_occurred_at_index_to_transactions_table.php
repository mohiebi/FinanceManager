<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serves the streak walk's `where user_id = ? and occurred_at >= ?`.
 *
 * The existing index is (user_id, type, occurred_at). With `type` in the middle
 * and unconstrained by this query, the range on `occurred_at` cannot use it —
 * so the dashboard would scan every one of the user's rows on each load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'occurred_at']);
        });
    }
};
