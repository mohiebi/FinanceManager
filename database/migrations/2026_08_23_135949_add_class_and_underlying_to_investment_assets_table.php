<?php

use App\Enums\AssetClass;
use App\Support\AssetFormulaReferences;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives an asset somewhere to say what it is, and what it tracks.
 *
 * Until now the table was flat, so "نیم سکه غیر بانکی", "Gold" and "شمش نقره" were
 * three unrelated things — and a portfolio that is 85% gold by exposure read to
 * the advisor as three tidy positions. `asset_class` groups by family and
 * `underlying_asset_id` by market, which is the pair needed to answer both
 * "how much is in metals" and "how much of that is gold".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('asset_class', 20)->nullable()->after('unit');
            $table->foreignId('underlying_asset_id')
                ->nullable()
                ->after('asset_class')
                ->constrained('investment_assets')
                ->nullOnDelete();
            // Units of the underlying per unit of this asset: 4.88 grams of gold
            // per half coin. Optional, because value can always be rolled up and
            // a quantity only sometimes can — "4 coin + 14 g" is not a number.
            $table->decimal('underlying_ratio', 20, 8)->nullable()->after('underlying_asset_id');

            $table->index('underlying_asset_id');
        });

        $assetIdsBySlug = DB::table('investment_assets')
            ->whereNull('user_id')
            ->pluck('id', 'slug');

        $defaultClasses = [
            'gold' => AssetClass::Metal,
            'silver' => AssetClass::Metal,
            'usd' => AssetClass::Currency,
            'eur' => AssetClass::Currency,
            'bitcoin' => AssetClass::Crypto,
        ];

        foreach ($defaultClasses as $slug => $class) {
            DB::table('investment_assets')
                ->whereNull('user_id')
                ->where('slug', $slug)
                ->update(['asset_class' => $class->value]);
        }

        $this->backfillCustomAssets($assetIdsBySlug->all());
    }

    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('underlying_asset_id');
            $table->dropColumn(['asset_class', 'underlying_ratio']);
        });
    }

    /**
     * Reads the exposure a formula-priced asset already states.
     *
     * Only formulas are touched, and only where they name exactly one market:
     * that is a fact the row already carries, not a guess. Everything else is
     * left null for its owner to fill in, because a wrong class here would be
     * invisible and would quietly skew the advice.
     *
     * @param  array<string, int>  $assetIdsBySlug
     */
    private function backfillCustomAssets(array $assetIdsBySlug): void
    {
        $references = app(AssetFormulaReferences::class);

        DB::table('investment_assets')
            ->whereNotNull('user_id')
            ->where('price_source_type', 'formula')
            ->orderBy('id')
            ->each(function (object $asset) use ($references, $assetIdsBySlug): void {
                $config = json_decode((string) $asset->price_source_config, true);
                $formula = is_array($config) ? trim((string) ($config['formula'] ?? '')) : '';

                if ($formula === '') {
                    return;
                }

                $slug = $references->soleAssetSlugIn($formula);
                $underlyingId = $slug === null ? null : ($assetIdsBySlug[$slug] ?? null);

                if ($underlyingId === null) {
                    return;
                }

                DB::table('investment_assets')->where('id', $asset->id)->update([
                    // Only inferred where tracking the market implies being the
                    // thing: nobody prices a non-gold item off the gold price, so
                    // a gold-tracking asset is metal. A dollar-tracking one is
                    // not currency — a watch valued at $500 tracks the dollar and
                    // is no more a currency holding than a house is. That one is
                    // left for its owner, who is the only one who knows.
                    'asset_class' => match ($slug) {
                        'gold', 'silver' => AssetClass::Metal->value,
                        'bitcoin' => AssetClass::Crypto->value,
                        default => null,
                    },
                    'underlying_asset_id' => $underlyingId,
                    'underlying_ratio' => $references->ratioToUnderlying($formula),
                ]);
            });
    }
};
