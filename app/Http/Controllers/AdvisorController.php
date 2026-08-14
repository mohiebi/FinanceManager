<?php

namespace App\Http\Controllers;

use App\Enums\InvestorAssessmentStatus;
use App\Http\Requests\Advisor\StoreAssessmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
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
