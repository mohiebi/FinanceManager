<?php

namespace App\Http\Controllers;

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Enums\Currency;
use App\Enums\Feature;
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
        $range = in_array($request->query('range'), ['1w', '1m', '3m', '1y', 'all'])
            ? (string) $request->query('range')
            : '1m';

        $common = [
            'currencies' => collect(Currency::cases())->map(fn (Currency $c) => [
                'label' => $c->label(),
                'value' => $c->value,
            ]),
            'selectedCurrency' => $selectedCurrency->value,
            'selectedRange' => $range,
            'pricesSyncedAt' => $priceService->lastSyncedAt(),
        ];

        // With the vault armed the server can read neither quantities nor cost
        // bases, so it ships the raw entries and lets the browser build the same
        // breakdown from decrypted values.
        //
        // `assets` and `summary` still travel, as plain empty props: they are what
        // <Deferred> waits on, and a key that never arrives leaves it showing a
        // spinner forever. The page reads its real values off vaultPortfolio.
        // Goals are their own module now, so the portfolio only carries them
        // when the user has it switched on.
        $showsGoals = $user->hasFeature(Feature::Goals);

        if ($user->vaultIsArmed()) {
            return Inertia::render('Portfolio', [
                ...$common,
                'vaultPortfolio' => $breakdownBuilder->clientPayload($user, $selectedCurrency),
                // Recent finishes only: the portfolio is for what is still in
                // play, and the goals page keeps the full record.
                'vaultGoals' => $showsGoals
                    ? $goalBuilder->clientPayload($user, recentOnly: true)
                    : null,
                'pricesAvailable' => $priceService->pricesAvailable(),
                'assets' => [],
                'summary' => null,
                // The vault can decrypt today's holdings client-side (see
                // vaultPortfolio above) but not a day-by-day history, so the
                // value-over-time chart simply has nothing to plot here.
                'chartData' => ['categories' => [], 'series' => []],
                // Explicit null rather than a missing key: the page tells a
                // deferred prop that has not landed apart from an empty list.
                'goals' => $showsGoals ? null : [],
                'showsGoals' => $showsGoals,
                'assetOptions' => Inertia::defer(fn () => $this->assetOptions($user)),
            ]);
        }

        $allEntries = $breakdownBuilder->entriesFor($user);

        return Inertia::render('Portfolio', [
            ...$common,
            'assets' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['assets']),
            'summary' => Inertia::defer(fn () => $breakdownBuilder->handle($allEntries, $selectedCurrency)['summary']),
            'chartData' => Inertia::defer(fn () => $breakdownBuilder->history($allEntries, $range, $priceService, $selectedCurrency)),
            'goals' => $showsGoals
                ? Inertia::defer(fn () => $goalBuilder->forPortfolio($user, $allEntries))
                : [],
            'showsGoals' => $showsGoals,
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
