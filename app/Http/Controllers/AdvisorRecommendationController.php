<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorRecommendationStatus;
use App\Http\Requests\Advisor\AnswerClarificationsRequest;
use App\Http\Requests\Advisor\GenerateRecommendationRequest;
use App\Http\Requests\Advisor\SealRecommendationRequest;
use App\Models\AdvisorRecommendation;
use App\Services\Advisor\AdvisorRecommendationService;
use App\Support\Encryption\EncryptedValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorRecommendationController extends Controller
{
    public function store(GenerateRecommendationRequest $request, AdvisorRecommendationService $service): JsonResponse
    {
        $profile = $request->user()->advisorProfiles()->with('assessment.answers')->latest()->firstOrFail();
        abort_if($profile->ai_consent_at === null, 422, __('advisor.validation.ai_consent_required'));

        return response()->json($service->start($request->user(), $profile));
    }

    public function clarify(AnswerClarificationsRequest $request, AdvisorRecommendation $recommendation, AdvisorRecommendationService $service): JsonResponse
    {
        abort_unless((int) $recommendation->user_id === (int) $request->user()->id, 404);
        abort_unless($recommendation->status === AdvisorRecommendationStatus::NeedsClarification, 409);
        $recommendation->load('profile.assessment.answers');

        return response()->json($service->answerClarifications(
            $request->user(),
            $recommendation,
            $request->validated('answers'),
            $request->validated('accepted_assets', []),
        ));
    }

    public function seal(SealRecommendationRequest $request, AdvisorRecommendation $recommendation): JsonResponse
    {
        abort_unless((int) $recommendation->user_id === (int) $request->user()->id, 404);
        abort_unless($request->user()->vaultIsArmed(), 409);
        abort_unless($recommendation->status === AdvisorRecommendationStatus::AwaitingVaultSeal, 409);
        abort_unless(hash_equals((string) $recommendation->output_hash, $request->validated('output_hash')), 422, __('advisor.validation.output_hash_mismatch'));

        $finalStatus = match ($recommendation->failure_code) {
            'pending_ready' => AdvisorRecommendationStatus::Ready,
            'pending_needs_clarification' => AdvisorRecommendationStatus::NeedsClarification,
            default => AdvisorRecommendationStatus::Failed,
        };
        $recommendation->forceFill([
            'recommendation_payload' => new EncryptedValue($request->validated('recommendation_payload'), 'recommendation_payload'),
            'status' => $finalStatus,
            'failure_code' => $finalStatus === AdvisorRecommendationStatus::Failed ? 'cannot_recommend' : null,
        ])->save();

        return response()->json(['status' => $finalStatus->value]);
    }

    public function show(Request $request, AdvisorRecommendation $recommendation): Response
    {
        abort_unless((int) $recommendation->user_id === (int) $request->user()->id, 404);
        $recommendation->load(['profile', 'messages']);

        return Inertia::render('Advisor/Recommendation', [
            'recommendation' => [
                'id' => $recommendation->id,
                'status' => $recommendation->status->value,
                'mode' => $recommendation->mode->value,
                'payload' => $recommendation->recommendation_payload,
                'output_hash' => $recommendation->output_hash,
                'failure_code' => $recommendation->failure_code,
                'generated_at' => $recommendation->generated_at?->toIso8601String(),
            ],
            'profile' => $recommendation->profile->profile_payload,
            'messages' => $recommendation->messages->map(fn ($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'payload' => $message->payload,
                'created_at' => $message->created_at->toIso8601String(),
            ]),
            'vaultArmed' => $request->user()->vaultIsArmed(),
        ]);
    }
}
