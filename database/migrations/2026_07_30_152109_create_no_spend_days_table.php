<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Days the user explicitly recorded as spend-free.
 *
 * A table of its own rather than a zero-amount transaction: a fake row would
 * land in every report, category chart and export, which is a high price for
 * one boolean. Nothing here is encrypted — "I spent nothing on this date"
 * leaks no more than `transactions.occurred_at` already does, and the streak
 * has to stay computable while the vault is armed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('no_spend_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            // Also the lookup index for the streak walk, via the leftmost prefix.
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('no_spend_days');
    }
};
