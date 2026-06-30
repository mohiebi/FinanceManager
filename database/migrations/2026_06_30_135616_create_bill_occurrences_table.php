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
        Schema::create('bill_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            $table->timestamp('reminder_day_before_sent_at')->nullable();
            $table->timestamp('reminder_due_day_sent_at')->nullable();

            $table->timestamps();

            $table->unique(['bill_id', 'due_date']);
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_occurrences');
    }
};
