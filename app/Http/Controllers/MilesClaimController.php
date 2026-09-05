<?php

namespace App\Http\Controllers;

use App\Actions\Miles\ClaimDailyMiles;
use App\Actions\Miles\MilesOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilesClaimController extends Controller
{
    public function __invoke(
        Request $request,
        ClaimDailyMiles $claimDailyMiles,
        MilesOverview $milesOverview,
    ): RedirectResponse {
        $day = $claimDailyMiles($request->user());
        $overview = $milesOverview($request->user()->refresh());

        // Flashed rather than derived in the browser: the step and reward are
        // decided server-side, and the client cannot infer them from a prop
        // that has already advanced to the next day.
        return back()
            ->with('status', __('miles.claimed', ['miles' => $day->claim_miles]))
            ->with('milesClaim', [
                'miles' => (int) $day->claim_miles,
                'step' => (int) $day->claim_step,
                'balance' => (int) $overview['balance'],
                'nextReward' => (int) $overview['nextClaimReward'],
            ]);
    }
}
