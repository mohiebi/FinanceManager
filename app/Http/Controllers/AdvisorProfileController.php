<?php

namespace App\Http\Controllers;

use App\Actions\Miles\ReserveAdvisorMiles;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorProfileController extends Controller
{
    public function __invoke(Request $request, ReserveAdvisorMiles $reserveAdvisorMiles): Response
    {
        $profile = $request->user()->advisorProfiles()->with('assessment')->latest()->firstOrFail();

        return Inertia::render('Advisor/Profile', [
            'profile' => [
                'id' => $profile->id,
                'profile_version' => $profile->profile_version,
                // Printed in the dossier's seal band beside the date: which
                // ruleset produced these scores is part of what is being sealed.
                'scoring_version' => $profile->assessment->scoring_version,
                'payload' => $profile->profile_payload,
                'ai_enabled' => $profile->ai_consent_at !== null,
                'completed_at' => $profile->assessment->completed_at?->toIso8601String(),
            ],
            'recommendationPricing' => [
                'full_miles' => $reserveAdvisorMiles->quote($request->user()),
                'guidance_miles' => (int) config('miles.advisor.guidance'),
                'charging' => (bool) config('miles.advisor_charging'),
            ],
        ]);
    }
}
