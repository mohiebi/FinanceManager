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
        Schema::create('mile_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->string('timezone', 64);
            $table->unsignedTinyInteger('claim_step')->nullable();
            $table->unsignedTinyInteger('claim_miles')->default(0);
            $table->unsignedTinyInteger('activity_miles')->default(0);
            $table->string('activity_source', 40)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'local_date']);
            $table->index(['user_id', 'claimed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mile_days');
    }
};
