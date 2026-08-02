<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One allocation rule inside a budget.
 *
 * `percent` is plaintext on purpose, and it is the property that makes this
 * feature work under the vault: a percentage is not money, so the server can
 * still validate a plan it cannot price — that the shares sum to at most 100,
 * that there is at most one remainder line, that no category is claimed twice.
 * Only `fixed_amount` is a monetary fact, so only it is sealed.
 *
 * `category_id` is nullable so a remainder line can mean "everything not
 * claimed above" without inventing a category to hang it on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('rule_type');
            // 5,2 covers 0.00 through 100.00. Never encrypted — see the note above.
            $table->decimal('percent', 5, 2)->nullable();
            $table->text('fixed_amount')->nullable();

            // Phase 2 honours this by carrying unspent room into the next period.
            // Stored from the start so enabling it later is not a migration.
            $table->boolean('rollover_enabled')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['budget_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
    }
};
