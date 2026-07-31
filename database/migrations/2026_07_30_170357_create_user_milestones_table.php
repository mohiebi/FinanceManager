<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per milestone a user has reached.
 *
 * The unique key is the whole mechanism: a milestone that fires twice is a
 * notification, and a milestone that fires once is a memory. Nothing here is
 * encrypted because nothing here is sensitive — a milestone key and a date say
 * only that someone kept records, never what they spent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_milestones');
    }
};
