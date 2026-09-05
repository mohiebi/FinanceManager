<?php

namespace App\Jobs;

use App\Actions\Miles\SettleAdvisorMiles;
use App\Enums\AdvisorRecommendationStatus;
use App\Models\AdvisorRecommendation;
use App\Services\Advisor\AdvisorRecommendationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one recommendation through the provider, off the request.
 *
 * This used to happen inside the HTTP request the buyer was sitting in, against
 * a provider timeout of five minutes. Anything that ended that request — a
 * closed tab, a locked phone, a flaky connection — threw away a completed
 * assessment with no record that it had ever been asked for.
 *
 * ShouldBeEncrypted because clarification answers ride along in the payload.
 * They are the user's own words about their finances, and the queue table is
 * not where they should sit in the clear.
 */
class GenerateAdvisorRecommendationJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt only. Every retry is another paid provider call against a
     * request the user is watching, and the failure states this job can reach
     * are already recorded on the recommendation itself.
     */
    public int $tries = 1;

    /**
     * Long enough for the whole provider call, carried on the job rather than
     * left to the worker's `--timeout` flag.
     *
     * A worker started without that flag kills its job after 60 seconds, which
     * is a fifth of what this one is allowed to take — and the queue this lands
     * on has no explicit onQueue(), so the worker that picks it up is whichever
     * one happens to drain `default`. The job's own timeout wins over the
     * worker's, so stating it here removes that dependency entirely.
     */
    public int $timeout;

    /**
     * @param  array<string, string|bool>  $clarificationAnswers
     * @param  array<int, array<string, mixed>>  $acceptedAssets
     */
    public function __construct(
        public readonly AdvisorRecommendation $recommendation,
        public readonly array $clarificationAnswers = [],
        public readonly array $acceptedAssets = [],
    ) {
        $this->timeout = $this->maximumSeconds();
    }

    /**
     * Outlive the provider call itself, or the worker would kill the job at the
     * exact moment the answer was about to arrive.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addSeconds($this->maximumSeconds());
    }

    /** The longest this job may reasonably occupy a worker. */
    public function maximumSeconds(): int
    {
        return (int) config('advisor.timeout') + (int) config('advisor.execution_time_buffer');
    }

    public function handle(AdvisorRecommendationService $service): void
    {
        $recommendation = $this->recommendation->fresh();

        if ($recommendation === null || $recommendation->status !== AdvisorRecommendationStatus::Generating) {
            return;
        }

        $recommendation->load('profile.assessment.answers', 'user');

        if ($this->clarificationAnswers === []) {
            $service->generate($recommendation->user, $recommendation);

            return;
        }

        $service->generateClarification(
            $recommendation->user,
            $recommendation,
            $this->clarificationAnswers,
            $this->acceptedAssets,
        );
    }

    /**
     * A crashed job must not leave a recommendation spinning forever. The page
     * polls on status, so anything other than a terminal state reads to the user
     * as still working.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Advisor recommendation generation failed.', [
            'recommendation_id' => $this->recommendation->id,
            'exception' => $exception?->getMessage(),
        ]);

        $recommendation = $this->recommendation->fresh();

        if ($recommendation === null || $recommendation->status !== AdvisorRecommendationStatus::Generating) {
            return;
        }

        $recommendation->forceFill([
            'status' => AdvisorRecommendationStatus::Failed,
            'pending_status' => null,
            'failure_code' => 'provider_failure',
            'generated_at' => now(),
        ])->save();
        app(SettleAdvisorMiles::class)($recommendation, 'failure');
    }
}
