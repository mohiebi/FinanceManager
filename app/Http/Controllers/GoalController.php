<?php

namespace App\Http\Controllers;

use App\Actions\Goals\BuildGoalProgress;
use App\Actions\Investments\BuildPortfolioBreakdown;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The goals page: every goal the user keeps, not just the ones worth surfacing
 * on the portfolio.
 *
 * The active/achieved split is left to the page rather than done here, because
 * `reached` is a fact about holdings — and under the vault the server cannot
 * read those. Splitting server-side would mean one arrangement for plaintext
 * users and none at all for armed ones.
 */
class GoalController extends Controller
{
    public function __invoke(
        Request $request,
        BuildGoalProgress $goalBuilder,
        BuildPortfolioBreakdown $breakdownBuilder,
    ): Response {
        $user = $request->user();

        if ($user->vaultIsArmed()) {
            return Inertia::render('Goals', [
                'goals' => null,
                'vaultGoals' => $goalBuilder->clientPayload($user),
                // Not deferred, unlike the portfolio's: this page exists to edit
                // goals, so the dialog is opened on most visits rather than few.
                'assetOptions' => $this->assetOptions($user),
            ]);
        }

        return Inertia::render('Goals', [
            'goals' => $goalBuilder->handle($user, $breakdownBuilder->entriesFor($user)),
            'vaultGoals' => null,
            'assetOptions' => $this->assetOptions($user),
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
