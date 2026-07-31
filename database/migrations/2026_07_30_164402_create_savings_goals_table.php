<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Savings goals denominated in an asset unit rather than money.
 *
 * "3 grams of gold by Nowruz", not "50,000,000 toman" — a toman target twelve
 * months out is fiction, and the asset is what the user is actually accumulating.
 * That also means no currency column and no conversion: progress is a ratio of
 * two quantities in the same unit, so it needs no price at all and keeps working
 * for manually-priced assets and through a price-feed outage.
 *
 * `target_quantity` is encrypted for the same reason `investments.quantity` is —
 * it is the same fact in a different tense. Leaving it plaintext would also
 * quietly undo the vault: the card shows progress as a percentage, and a target
 * times a percentage is the holding the user paid to keep private.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_asset_id')->constrained()->cascadeOnDelete();

            $table->text('title')->nullable();
            // Text, not string: base64 ciphertext outgrows a 255-char column.
            $table->text('target_quantity');

            $table->date('target_date');
            // The pace baseline. Holdings bought before this are the starting
            // point, not progress the user made toward the goal.
            $table->date('started_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
