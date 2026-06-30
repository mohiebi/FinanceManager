<?php

namespace App\Jobs;

use App\Services\AssetPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    }
}
