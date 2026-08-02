<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The currency a fixed line's amount is written in.
 *
 * Rent quoted in toman and a subscription billed in dollars belong in the same
 * plan, so a fixed amount cannot be assumed to share the budget's currency. The
 * conversion into that currency happens when the plan is resolved, against the
 * same public rates the rest of the app uses.
 *
 * Nullable, and null means "the budget's own currency" — percentage and
 * remainder lines carry no amount to denominate, and a line written before this
 * column existed was already in the budget's currency by definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table): void {
            $table->string('currency')->nullable()->after('fixed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
