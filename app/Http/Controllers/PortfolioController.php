<?php

namespace App\Http\Controllers;

use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Currency;
use App\Services\AssetPriceService;
use App\Support\CurrencyPreference;
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
        $selectedCurrency = CurrencyPreference::resolve($request);

        $common = [
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ];

        // With the vault armed the server can read neither quantities nor cost
        // bases, so it ships the raw entries and lets the browser build the same
        // breakdown from decrypted values.
        //
        // `assets` and `summary` still travel, as plain empty props: they are what
        // <Deferred> waits on, and a key that never arrives leaves it showing a
        // spinner forever. The page reads its real values off vaultPortfolio.
        if ($user->vaultIsArmed()) {
            return Inertia::render('Portfolio', [
                ...$common,
                'vaultPortfolio' => $breakdownBuilder->clientPayload($user, $selectedCurrency),
                'pricesAvailable' => $priceService->pricesAvailable(),
                'assets' => [],
                'summary' => null,
            ]);
        }

        $allEntries = $breakdownBuilder->entriesFor($user);

        return Inertia::render('Portfolio', [
            ...$common,
            'assets' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['assets']),
            'summary' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['summary']),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
        ]);
    }
}
