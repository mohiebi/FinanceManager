<?php

use App\Support\BudgetMath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A budget: a named set of allocation rules applied to one calendar month.
 *
 * Deliberately not a table of per-category amounts. A flat envelope is
 * meaningless to anyone whose income varies month to month, so the amounts live
 * on `budget_lines` as rules and are resolved against the period's real income —
 * see {@see BudgetMath}.
 *
 * `income_basis` picks what a percentage is a percentage *of*. `actual` bases it
 * on income recorded so far, which is honest for irregular earners but reads
 * zero on the first of the month; `expected` bases it on a figure the user
 * declares, so targets exist from day one. `expected_income` is encrypted for
 * the obvious reason that it is money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Text, not string: base64 ciphertext outgrows a 255-char column.
            $table->text('title')->nullable();

            $table->string('income_basis')->default('actual');
            $table->text('expected_income')->nullable();

            $table->string('currency')->default('toman');
            // The first period this budget applies to. Months before it are not
            // "missed" — the plan simply did not exist yet.
            $table->date('starts_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
