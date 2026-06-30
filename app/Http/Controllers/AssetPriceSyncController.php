<?php

namespace App\Http\Controllers;

use App\Services\AssetPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetPriceSyncController extends Controller
{
    public function __invoke(Request $request, AssetPriceService $priceService): RedirectResponse
    {
        $priceService->invalidateCache($request->user());

        return back();
    }
}
