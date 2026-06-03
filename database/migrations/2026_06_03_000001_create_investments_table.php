<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Asset type: gold, silver, usd, eur, coin, bitcoin
            $table->string('asset_type', 20);

            // How much of the asset was added on this date
            $table->decimal('quantity', 16, 8);

            // Cost basis — what the user paid per unit at purchase time.
            // Stored but not yet surfaced in the UI (reserved for Portfolio page later).
            $table->decimal('cost_basis', 16, 4)->nullable()->comment('Price paid per unit at purchase');
            $table->string('cost_basis_currency', 10)->nullable();

            $table->text('note')->nullable();
            $table->date('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'asset_type']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
