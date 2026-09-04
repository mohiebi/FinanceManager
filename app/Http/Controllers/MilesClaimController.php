<?php

namespace App\Http\Controllers;

use App\Actions\Miles\ClaimDailyMiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilesClaimController extends Controller
{
    public function __invoke(Request $request, ClaimDailyMiles $claimDailyMiles): RedirectResponse
    {
        $day = $claimDailyMiles($request->user());

        return back()->with('status', __('miles.claimed', ['miles' => $day->claim_miles]));
    }
}
