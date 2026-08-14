<?php

namespace App\Services\Advisor;

class AdvisorExecutionTimeLimiter
{
    public function __construct(
        private readonly ?int $providerTimeout = null,
        private readonly ?int $executionTimeBuffer = null,
    ) {}

    public function extendForProviderCall(): void
    {
        $currentLimit = (int) ini_get('max_execution_time');
        $requiredLimit = $this->requiredSeconds();

        if ($currentLimit > 0 && $currentLimit < $requiredLimit) {
            set_time_limit($requiredLimit);
        }
    }

    public function requiredSeconds(): int
    {
        $providerTimeout = max(1, $this->providerTimeout ?? (int) config('advisor.timeout'));
        $executionBuffer = max(5, $this->executionTimeBuffer ?? (int) config('advisor.execution_time_buffer'));

        return $providerTimeout + $executionBuffer;
    }
}
