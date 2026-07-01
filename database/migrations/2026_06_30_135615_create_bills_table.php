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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            // Encrypted at rest, same as Investment quantity/cost_basis — bills reveal
            // financial info too. Stored as text since ciphertext is a variable-length string.
            $table->text('amount');
            $table->string('currency', 10);

            // 'one_time' | 'monthly'
            $table->string('recurrence_type', 20);

            // For monthly recurrence: day of month (1-31), clamped to the last day of
            // shorter months. Interpreted in the user's calendar (gregorian/jalali) at
            // occurrence-generation time.
            $table->unsignedTinyInteger('due_day_of_month')->nullable();

            // For one-time bills: the single due date.
            $table->date('due_date')->nullable();

            $table->boolean('telegram_reminder_enabled')->default(true);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
