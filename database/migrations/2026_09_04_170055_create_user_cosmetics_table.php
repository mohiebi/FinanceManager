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
        Schema::create('user_cosmetics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('type', 24);
            $table->boolean('selected')->default(false);
            $table->foreignUlid('mile_ledger_entry_id')->nullable()->constrained('mile_ledger_entries')->nullOnDelete();
            $table->timestamp('acquired_at');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'type', 'selected']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cosmetics');
    }
};
