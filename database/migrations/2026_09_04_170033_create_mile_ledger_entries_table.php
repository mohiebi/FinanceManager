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
        Schema::create('mile_ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason', 40);
            $table->nullableMorphs('source');
            $table->string('idempotency_key', 191);
            $table->ulid('transfer_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mile_ledger_entries');
    }
};
