<?php

namespace App\Http\Controllers;

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Currency;
use App\Models\InvestmentAsset;
use App\Models\User;
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
        BuildGoalProgress $goalBuilder,
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
                'vaultGoals' => $goalBuilder->clientPayload($user),
                'pricesAvailable' => $priceService->pricesAvailable(),
                'assets' => [],
                'summary' => null,
                'goals' => null,
                'assetOptions' => Inertia::defer(fn () => $this->assetOptions($user)),
            ]);
        }

        $allEntries = $breakdownBuilder->entriesFor($user);

        return Inertia::render('Portfolio', [
            ...$common,
            'assets' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['assets']),
            'summary' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['summary']),
            'goals' => Inertia::defer(fn () => $goalBuilder->handle($user, $allEntries)),
            'pricesAvailable' => Inertia::defer(fn () => $priceService->pricesAvailable()),
            // Deferred: only the create-goal dialog reads this, and most visits
            // never open it.
            'assetOptions' => Inertia::defer(fn () => $this->assetOptions($user)),
        ]);
    }

    /**
     * Assets a goal may be denominated in — the same set the investment form
     * offers, so a user cannot set a goal on something they cannot record.
     *
     * @return array<int, array{id: int, label: string, unit: string}>
     */
    private function assetOptions(User $user): array
    {
        return InvestmentAsset::query()
            ->availableFor($user)
            ->orderBy('name')
            ->get()
            ->map(fn (InvestmentAsset $asset): array => [
                'id' => $asset->id,
                'label' => $asset->label(),
                'unit' => $asset->unit,
            ])
            ->all();
    }
}
