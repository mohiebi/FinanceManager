<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshAssetPricesJob;
use App\Services\AssetPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AssetPriceSyncController extends Controller
{
    private const COOLDOWN_SECONDS = 300;

    public function __invoke(Request $request, AssetPriceService $priceService): RedirectResponse
    {
        $lastSyncedAt = $priceService->lastSyncedAt();

        if ($lastSyncedAt !== null) {
            $secondsSinceSync = Carbon::parse($lastSyncedAt)->diffInSeconds(now());

            if ($secondsSinceSync < self::COOLDOWN_SECONDS) {
                $remainingMinutes = (int) ceil((self::COOLDOWN_SECONDS - $secondsSinceSync) / 60);

                throw ValidationException::withMessages([
                    'sync' => trans_choice('finance.sync_too_soon', $remainingMinutes, ['minutes' => $remainingMinutes]),
                ]);
            }
        }

        $priceService->invalidateCache($request->user());

        RefreshAssetPricesJob::dispatch();

        return back();
    }
}
