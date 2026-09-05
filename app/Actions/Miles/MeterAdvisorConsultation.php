<?php

namespace App\Actions\Miles;

use App\Enums\MilesReason;
use App\Models\AdvisorMessage;

final readonly class MeterAdvisorConsultation
{
    public function __construct(private AdjustMiles $adjustMiles) {}

    public function reserve(AdvisorMessage $message): int
    {
        $price = (int) config('miles.advisor.consultation');

        if (! config('miles.advisor_charging')) {
            return 0;
        }

        ($this->adjustMiles)(
            $message->user,
            -$price,
            MilesReason::AdvisorReservation,
            "advisor-message:{$message->getKey()}:reserve",
            $message,
            ['service' => 'consultation'],
            action: 'advisor_consultation',
        );

        return $price;
    }

    public function refund(AdvisorMessage $message, int $charged): void
    {
        if ($charged === 0) {
            return;
        }

        ($this->adjustMiles)(
            $message->user,
            $charged,
            MilesReason::AdvisorRefund,
            "advisor-message:{$message->getKey()}:refund",
            $message,
            ['service' => 'consultation'],
        );
    }
}
