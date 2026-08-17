<?php

namespace App\Support\Billing;

use App\Enums\ScreeningRisk;
use Carbon\CarbonImmutable;

final readonly class ScreeningResult
{
    /** @param  array<int, string>  $categories */
    public function __construct(
        public ScreeningRisk $risk,
        public string $provider,
        public array $categories = [],
        public ?string $providerReference = null,
        public ?string $errorCode = null,
        public ?CarbonImmutable $screenedAt = null,
    ) {}

    public static function unknown(string $provider, string $errorCode): self
    {
        return new self(
            risk: ScreeningRisk::Unknown,
            provider: $provider,
            errorCode: $errorCode,
            screenedAt: CarbonImmutable::now(),
        );
    }

    public function timestamp(): CarbonImmutable
    {
        return $this->screenedAt ?? CarbonImmutable::now();
    }
}
