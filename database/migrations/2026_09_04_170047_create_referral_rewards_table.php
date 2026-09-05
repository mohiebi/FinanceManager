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
        Schema::create('referral_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 24);
            $table->foreignUlid('referrer_ledger_entry_id')->nullable()->constrained('mile_ledger_entries')->nullOnDelete();
            $table->foreignUlid('friend_ledger_entry_id')->nullable()->constrained('mile_ledger_entries')->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['referral_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
