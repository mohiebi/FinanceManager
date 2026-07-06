<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_asset_id')
                ->constrained('investment_assets')
                ->cascadeOnDelete();
            // Toman prices can be very large (BTC ~ tens of billions).
            $table->decimal('price', 24, 4);
            $table->date('snapped_on');
            $table->timestamps();

            // One snapshot per asset per day; the hourly job upserts.
            $table->unique(['investment_asset_id', 'snapped_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_price_snapshots');
    }
};
