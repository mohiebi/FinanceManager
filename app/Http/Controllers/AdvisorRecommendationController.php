<?php

namespace App\Http\Controllers;

use App\Actions\Miles\SettleAdvisorMiles;
use App\Enums\AdvisorRecommendationStatus;
use App\Http\Requests\Advisor\AnswerClarificationsRequest;
use App\Http\Requests\Advisor\GenerateRecommendationRequest;
use App\Http\Requests\Advisor\SealRecommendationRequest;
use App\Models\AdvisorRecommendation;
use App\Services\Advisor\AdvisorPendingPayloadStore;
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
        $profile = $request->user()->advisorProfiles()
            ->with('assessment.answers')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->firstOrFail();
        abort_if($profile->ai_consent_at === null, 422, __('advisor.validation.ai_consent_required'));

        $recommendation = $service->open($request->user(), $profile);

        /*
         * Accepted, not finished. The provider call now runs in a queued job, so
         * the browser's next move is to open the recommendation page and watch
         * it rather than hold a request open for up to five minutes.
         */
        return response()->json([
            'recommendation_id' => $recommendation->id,
            'status' => $recommendation->status->value,
        ], 202);
    }

    public function clarify(AnswerClarificationsRequest $request, AdvisorRecommendation $recommendation, AdvisorRecommendationService $service): JsonResponse
    {
        abort_unless($recommendation->status === AdvisorRecommendationStatus::NeedsClarification, 409);
        $recommendation->load('profile.assessment.answers');

        $failure = $service->openClarification(
            $request->user(),
            $recommendation,
            $request->validated('answers'),
            $request->validated('accepted_assets', []),
        );

        if ($failure !== null) {
            return response()->json($failure, 422);
        }

        return response()->json([
            'recommendation_id' => $recommendation->id,
            'status' => $recommendation->fresh()->status->value,
        ], 202);
    }

    /**
     * Hand a Vault-armed browser the payload its queued job produced.
     *
     * Only reachable while the recommendation is waiting to be sealed, and the
     * parked copy is dropped as soon as the sealed ciphertext lands in seal().
     */
    public function claim(Request $request, AdvisorRecommendation $recommendation, AdvisorPendingPayloadStore $pendingPayloads, SettleAdvisorMiles $settleAdvisorMiles): JsonResponse
    {
        abort_unless($request->user()->vaultIsArmed(), 409);
        abort_unless($recommendation->status === AdvisorRecommendationStatus::AwaitingVaultSeal, 409);

        $payload = $pendingPayloads->peek($recommendation);

        if ($payload === null) {
            /*
             * The holding window closed before the user came back. Nothing can
             * recover the plaintext, so retire the row rather than leaving a
             * recommendation that can never be opened.
             */
            $recommendation->forceFill([
                'status' => AdvisorRecommendationStatus::Failed,
                'pending_status' => null,
                'failure_code' => 'pending_payload_expired',
            ])->save();
            $settleAdvisorMiles($recommendation, 'failure');

            return response()->json(['status' => 'failed', 'failure_code' => 'pending_payload_expired'], 410);
        }

        return response()->json([
            'recommendation_id' => $recommendation->id,
            'status' => $recommendation->pending_status?->value ?? AdvisorRecommendationStatus::Ready->value,
            'payload' => $payload,
            'output_hash' => $recommendation->output_hash,
            'vault_seal_required' => true,
        ]);
    }

    public function seal(SealRecommendationRequest $request, AdvisorRecommendation $recommendation, AdvisorPendingPayloadStore $pendingPayloads): JsonResponse
    {
        abort_unless($request->user()->vaultIsArmed(), 409);
        abort_unless($recommendation->status === AdvisorRecommendationStatus::AwaitingVaultSeal, 409);

        $finalStatus = $recommendation->pending_status;
        abort_unless($finalStatus instanceof AdvisorRecommendationStatus
            && in_array($finalStatus, [
                AdvisorRecommendationStatus::Ready,
                AdvisorRecommendationStatus::NeedsClarification,
                AdvisorRecommendationStatus::Failed,
            ], true), 409);
        abort_unless(is_string($recommendation->output_hash)
            && preg_match('/\A[a-f0-9]{64}\z/', $recommendation->output_hash) === 1, 409);

        /*
         * The server deliberately cannot decrypt this Vault ciphertext. Its
         * server-generated output_hash remains unchanged so the browser can
         * verify the plaintext immediately after decrypting saved history.
         */
        $recommendation->forceFill([
            'recommendation_payload' => new EncryptedValue($request->validated('recommendation_payload'), 'recommendation_payload'),
            'status' => $finalStatus,
            'pending_status' => null,
            'failure_code' => $finalStatus === AdvisorRecommendationStatus::Failed
                ? ($recommendation->failure_code ?? 'cannot_recommend')
                : null,
        ])->save();

        // The browser holds the only readable copy from here on.
        $pendingPayloads->forget($recommendation);

        return response()->json(['status' => $finalStatus->value]);
    }

    public function show(Request $request, AdvisorRecommendation $recommendation): Response
    {
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
                /*
                 * Enough for the page to describe what is happening while a job
                 * works, without inventing progress it cannot see. Both counters
                 * move before the work they describe, so they read as a stage.
                 */
                'provider_calls' => $recommendation->provider_calls,
                'repair_attempts' => $recommendation->repair_attempts,
                'created_at' => $recommendation->created_at->toIso8601String(),
                'quoted_miles' => $recommendation->quoted_miles,
                'charged_miles' => $recommendation->charged_miles,
                'miles_outcome' => $recommendation->miles_outcome,
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
