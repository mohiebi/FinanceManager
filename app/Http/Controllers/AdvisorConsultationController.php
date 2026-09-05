<?php

namespace App\Http\Controllers;

use App\Actions\Miles\MeterAdvisorConsultation;
use App\Actions\Miles\RecordServiceUsage;
use App\Ai\Agents\AdvisorConsultationAgent;
use App\Enums\AdvisorRecommendationStatus;
use App\Http\Requests\Advisor\ConsultAdvisorRequest;
use App\Models\AdvisorRecommendation;
use App\Services\Advisor\AdvisorCanonicalJson;
use App\Services\Advisor\AdvisorExecutionTimeLimiter;
use App\Support\Encryption\EncryptedValue;
use App\Support\Encryption\SealedField;
use App\Support\FrontendLocalization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AdvisorConsultationController extends Controller
{
    public function store(
        ConsultAdvisorRequest $request,
        AdvisorRecommendation $recommendation,
        AdvisorCanonicalJson $canonicalJson,
        AdvisorExecutionTimeLimiter $executionTimeLimiter,
        MeterAdvisorConsultation $meterAdvisorConsultation,
        RecordServiceUsage $recordServiceUsage,
    ): JsonResponse {
        abort_unless($recommendation->status === AdvisorRecommendationStatus::Ready, 409);
        $recommendation->loadMissing('profile');
        $vaultArmed = $request->user()->vaultIsArmed();
        $sealedMessageRules = $vaultArmed ? SealedField::rules() : ['nullable'];
        $validated = Validator::make($request->all(), [
            'recommendation_context' => [$vaultArmed ? 'required' : 'nullable', 'array'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1500'],
            'sealed_message' => $sealedMessageRules,
        ])->validate();

        $userMessage = ['content' => $request->validated('message')];
        $recommendationPayload = $vaultArmed ? $validated['recommendation_context'] : $recommendation->recommendation_payload;

        [$message, $charged] = DB::transaction(function () use ($vaultArmed, $recommendation, $request, $validated, $userMessage, $meterAdvisorConsultation): array {
            $message = $recommendation->messages()->create([
                'user_id' => $request->user()->id,
                'role' => 'user',
                'payload' => $vaultArmed
                    ? new EncryptedValue($validated['sealed_message'], 'payload')
                    : $userMessage,
            ]);

            return [$message, $meterAdvisorConsultation->reserve($message)];
        }, 3);

        $context = [
            'investor_profile' => $recommendation->profile->profile_payload,
            'validated_recommendation' => $recommendationPayload,
            'conversation' => $vaultArmed
                ? ($validated['history'] ?? [])
                : $recommendation->messages()->get()->map(fn ($message): array => [
                    'role' => $message->role,
                    'content' => $message->payload['content'] ?? $message->payload['answer'] ?? '',
                ])->all(),
            'user_question' => $request->validated('message'),
            'knowledge_mode' => 'model_only',
            'response_language' => FrontendLocalization::languageName($request->user()->locale),
        ];

        try {
            $startedAt = hrtime(true);
            $executionTimeLimiter->extendForProviderCall();
            $providerResponse = AdvisorConsultationAgent::make()->prompt(
                $canonicalJson->encode($context),
                provider: (string) config('advisor.provider'),
                model: config('advisor.model'),
                timeout: (int) config('advisor.timeout'),
            );
            $recordServiceUsage(
                $request->user(),
                'consultation',
                'success',
                $providerResponse,
                $message,
                $startedAt,
                (int) config('miles.advisor.consultation'),
                $charged,
                ['provider_attempted' => true],
            );
            $response = $providerResponse->toArray();
        } catch (Throwable $exception) {
            $meterAdvisorConsultation->refund($message, $charged);
            $recordServiceUsage(
                $request->user(),
                'consultation',
                'failure',
                null,
                $message,
                $startedAt ?? hrtime(true),
                (int) config('miles.advisor.consultation'),
                metadata: ['exception' => $exception::class, 'provider_attempted' => true],
            );

            return response()->json(['message' => __('advisor.validation.provider_failure')], 502);
        }

        if (! $vaultArmed) {
            $recommendation->messages()->create(['user_id' => $request->user()->id, 'role' => 'assistant', 'payload' => $response]);
        }

        return response()->json(['payload' => $response, 'vault_seal_required' => $vaultArmed]);
    }

    public function seal(Request $request, AdvisorRecommendation $recommendation): JsonResponse
    {
        abort_unless($request->user()->vaultIsArmed(), 409);
        $validated = Validator::make($request->all(), ['payload' => SealedField::rules(), 'role' => ['required', 'in:assistant']])->validate();
        $message = $recommendation->messages()->create([
            'user_id' => $request->user()->id,
            'role' => 'assistant',
            'payload' => new EncryptedValue($validated['payload'], 'payload'),
        ]);

        return response()->json(['id' => $message->id]);
    }
}
