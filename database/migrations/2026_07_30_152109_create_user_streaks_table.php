<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The small amount of streak state that cannot be derived.
 *
 * The current run is deliberately absent: it is recomputed from
 * `transactions.occurred_at` on every read, because bulk deletes and the CSV
 * importer both bypass model events and would silently desynchronise a stored
 * counter. What lives here is only what a walk over the dates cannot recover —
 * the all-time high-water mark, which gaps were forgiven, and when the last
 * nudge went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_streaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('best_run')->default(0);
            $table->date('best_run_ended_on')->nullable();
            // Which specific gaps were forgiven, so recomputing the same history
            // twice cannot forgive a different day and change the answer.
            $table->json('grace_dates')->nullable();
            $table->date('last_nudged_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_streaks');
    }
};
