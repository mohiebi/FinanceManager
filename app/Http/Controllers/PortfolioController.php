<?php

namespace App\Http\Controllers;

use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Currency;
use App\Services\AssetPriceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function __invoke(
        Request $request,
        AssetPriceService $priceService,
        BuildPortfolioBreakdown $breakdownBuilder,
    ): Response {
        $user = $request->user();
        $selectedCurrency = Currency::tryFrom((string) $request->query('currency')) ?? Currency::Toman;

        $allEntries = $breakdownBuilder->entriesFor($user);

        return Inertia::render('Portfolio', [
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'assets' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['assets']),
            'summary' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['summary']),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ]);
    }
}
