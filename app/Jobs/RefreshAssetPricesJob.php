<?php

namespace App\Jobs;

use App\Models\AssetPriceSnapshot;
use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class RefreshAssetPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct()
    {
        $this->onQueue('prices');
    }

    public function handle(AssetPriceService $priceService): void
    {
        $priceService->refreshTgjuPrices();

        $this->snapshotPrices($priceService);
    }

    /**
     * Persist one price point per asset per day so historical charts can use
     * real prices instead of re-pricing history at today's rate. The job runs
     * hourly; the unique (asset, day) upsert keeps the last price of the day.
     */
    private function snapshotPrices(AssetPriceService $priceService): void
    {
        // Pass a Carbon instance (not a Y-m-d string) so the match query and the
        // stored value serialize identically across SQLite and MySQL.
        $today = Carbon::today();

        InvestmentAsset::query()
            ->get()
            ->each(function (InvestmentAsset $asset) use ($priceService, $today): void {
                if (! $priceService->priceAvailableFor($asset)) {
                    return;
                }

                AssetPriceSnapshot::query()->updateOrCreate(
                    ['investment_asset_id' => $asset->id, 'snapped_on' => $today],
                    ['price' => $priceService->priceFor($asset)],
                );
            });
    }
}
