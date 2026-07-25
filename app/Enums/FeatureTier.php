<?php

namespace App\Enums;

enum FeatureTier: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function label(): string
    {
        $translationKey = "modules.tiers.{$this->value}";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }
}
