<?php

namespace App\Http\Controllers;

use App\Actions\Miles\ChargeAdvisorAssessment;
use App\Enums\Feature;
use App\Enums\InvestorAssessmentStatus;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Requests\Advisor\StoreAssessmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorController extends Controller
{
    /**
     * The Advisor landing page, or the paywall standing in front of it.
     *
     * This route is not behind {@see EnsureFeatureEnabled}
     * because the two ways of not having the Advisor need different answers:
     * a user who simply switched the module off can be sent to the modules page
     * to switch it back on, but a user without the Pro entitlement would find
     * only a locked row there. They get the paywall.
     */
    public function index(Request $request, ChargeAdvisorAssessment $chargeAdvisorAssessment): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->mayUse(Feature::Advisor)) {
            return Inertia::render('Advisor/Paywall', [
                /*
                 * A lapsed subscriber still has their assessment and their
                 * derived profile. Saying so turns the paywall from a wall into
                 * a renewal — and it is true whether they lapsed or an admin
                 * granted them a trial that ran out.
                 */
                'hasProfile' => $user->advisorProfiles()->exists(),
            ]);
        }

        if (! $user->hasFeature(Feature::Advisor)) {
            return redirect()->route('modules.edit')
                ->with('status', __('modules.locked', ['module' => Feature::Advisor->label()]));
        }

        $assessment = $user->investorAssessments()->latest()->first();
        $profile = $user->advisorProfiles()->latest()->first();
        $recommendations = $user->advisorRecommendations()->latest()->limit(5)->get();

        return Inertia::render('Advisor/Index', [
            'assessment' => $assessment === null ? null : [
                ...$assessment->only(['id', 'last_completed_section', 'completed_at']),
                'status' => $assessment->status->value,
            ],
            'profile' => $profile?->only(['id', 'profile_version', 'created_at', 'ai_consent_at']),
            'recommendations' => $recommendations->map(fn ($recommendation): array => [
                'id' => $recommendation->id,
                'status' => $recommendation->status->value,
                'mode' => $recommendation->mode->value,
                'generated_at' => $recommendation->generated_at?->toIso8601String(),
            ]),
            'assessmentPricing' => [
                'miles' => $chargeAdvisorAssessment->quote($user),
                'charging' => (bool) config('miles.advisor_charging'),
            ],
        ]);
    }

    public function store(StoreAssessmentRequest $request): RedirectResponse
    {
        $assessment = $request->user()->investorAssessments()->create([
            'status' => InvestorAssessmentStatus::InProgress,
            'assessment_version' => 1,
            'scoring_version' => 1,
            'last_completed_section' => 0,
            'started_at' => now(),
        ]);

        return redirect()->route('advisor.assessments.show', $assessment);
    }
}
