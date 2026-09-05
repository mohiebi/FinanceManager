<?php

namespace App\Actions\Miles;

use App\Models\ServiceUsageEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class RecordServiceUsage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        ?User $user,
        string $operation,
        string $outcome,
        ?object $response,
        ?Model $source,
        int $startedAt,
        int $shadowMiles,
        int $chargedMiles = 0,
        array $metadata = [],
    ): ServiceUsageEvent {
        $providerAttempted = (bool) ($metadata['provider_attempted'] ?? false);
        $provider = $response?->meta?->provider ?? ($providerAttempted ? config('advisor.provider') : null);
        $model = $response?->meta?->model ?? ($providerAttempted ? config('advisor.model') : null);
        $promptTokens = (int) ($response?->usage?->promptTokens ?? 0);
        $completionTokens = (int) ($response?->usage?->completionTokens ?? 0);
        $rates = config("miles.advisor.provider_rates.{$provider}.{$model}");
        $cost = is_array($rates)
            ? (($promptTokens * (float) ($rates['prompt'] ?? 0)) + ($completionTokens * (float) ($rates['completion'] ?? 0))) / 1_000_000
            : null;

        return ServiceUsageEvent::query()->create([
            'user_id' => $user?->getKey(),
            'service' => 'advisor',
            'operation' => $operation,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'provider_cost_usd' => $cost,
            'latency_ms' => max(0, (int) ((hrtime(true) - $startedAt) / 1_000_000)),
            'outcome' => $outcome,
            'shadow_miles' => $shadowMiles,
            'charged_miles' => $chargedMiles,
            'metadata' => [...$metadata, 'rate_snapshot' => $rates],
        ]);
    }
}
