<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('investment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('unit', 20);
            $table->string('icon', 20)->nullable();
            $table->string('color', 20)->default('#02CD86');
            $table->boolean('is_default')->default(false);
            $table->string('price_source_type', 20)->default('manual');
            $table->json('price_source_config')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'slug']);
            $table->index('is_default');
        });

        $now = now();
        DB::table('investment_assets')->insert([
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'unit' => 'g',
                'icon' => '🥇',
                'color' => '#F59E0B',
                'is_default' => true,
                'price_source_type' => 'builtin',
                'price_source_config' => json_encode(['key' => 'gold']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'unit' => 'g',
                'icon' => '🥈',
                'color' => '#6B7280',
                'is_default' => true,
                'price_source_type' => 'builtin',
                'price_source_config' => json_encode(['key' => 'silver']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'US Dollar',
                'slug' => 'usd',
                'unit' => 'USD',
                'icon' => '💵',
                'color' => '#0EA5E9',
                'is_default' => true,
                'price_source_type' => 'builtin',
                'price_source_config' => json_encode(['key' => 'usd']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Euro',
                'slug' => 'eur',
                'unit' => 'EUR',
                'icon' => '💶',
                'color' => '#3B82F6',
                'is_default' => true,
                'price_source_type' => 'builtin',
                'price_source_config' => json_encode(['key' => 'eur']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Bitcoin',
                'slug' => 'bitcoin',
                'unit' => 'BTC',
                'icon' => '₿',
                'color' => '#F97316',
                'is_default' => true,
                'price_source_type' => 'builtin',
                'price_source_config' => json_encode(['key' => 'bitcoin']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('investment_asset_id')
                ->nullable()
                ->after('user_id')
                ->constrained('investment_assets');
        });

        $assetIds = DB::table('investment_assets')->pluck('id', 'slug');
        foreach ($assetIds as $slug => $assetId) {
            DB::table('investments')
                ->where('asset_type', $slug)
                ->update(['investment_asset_id' => $assetId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_asset_id');
        });

        Schema::dropIfExists('investment_assets');
    }
};
