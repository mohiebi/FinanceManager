<?php

namespace App\Enums;

/**
 * A togglable area of the app.
 *
 * Core features are always on and never rendered as toggles. Everything else is
 * opt-in per user, defaulting to {@see self::enabledByDefault()} when the user
 * has no `user_features` row — rows are sparse overrides, not the source of truth.
 */
enum Feature: string
{
    case Transactions = 'transactions';
    case Reports = 'reports';
    case Bills = 'bills';
    case Investments = 'investments';
    case Portfolio = 'portfolio';
    case AiAssistant = 'ai_assistant';
    case TelegramBot = 'telegram_bot';

    public function label(): string
    {
        $translationKey = "modules.{$this->value}.label";
        $translatedLabel = __($translationKey);

        if ($translatedLabel !== $translationKey) {
            return $translatedLabel;
        }

        return match ($this) {
            self::Transactions => 'Transactions',
            self::Reports => 'Reports',
            self::Bills => 'Bills',
            self::Investments => 'Investments',
            self::Portfolio => 'Portfolio',
            self::AiAssistant => 'AI Assistant',
            self::TelegramBot => 'Telegram Bot',
        };
    }

    public function description(): string
    {
        $translationKey = "modules.{$this->value}.description";
        $translatedDescription = __($translationKey);

        return $translatedDescription !== $translationKey ? $translatedDescription : '';
    }

    /** Core features are always enabled and cannot be toggled off. */
    public function isCore(): bool
    {
        return match ($this) {
            self::Transactions, self::Reports => true,
            default => false,
        };
    }

    /**
     * Plan entitlement — whether the user *may* use this at all.
     *
     * Deliberately separate from enablement (whether they *chose* to use it), so a
     * paid tier can be introduced by changing arms here without touching anything else.
     */
    public function tier(): FeatureTier
    {
        return match ($this) {
            default => FeatureTier::Free,
        };
    }

    /**
     * Features that must be enabled for this one to work.
     *
     * @return array<int, self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::Portfolio => [self::Investments],
            self::Bills, self::Reports => [self::Transactions],
            default => [],
        };
    }

    /**
     * Features that cannot be enabled at the same time as this one.
     *
     * Treated as symmetric by the resolver, so only one side of a pair needs declaring.
     *
     * @return array<int, self>
     */
    public function conflictsWith(): array
    {
        return match ($this) {
            default => [],
        };
    }

    /**
     * The reverse dependency edge — features that break if this one is turned off.
     *
     * @return array<int, self>
     */
    public function requiredBy(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $feature): bool => in_array($this, $feature->requires(), true),
        ));
    }

    public function enabledByDefault(): bool
    {
        return $this->isCore();
    }

    /**
     * Whether a disabled module advertises itself in the nav by default.
     *
     * Always true — discovery is the whole point of the promo state, and it matches
     * the `show_promo` column default. Only ever consulted while a feature is
     * disabled, so it says nothing about modules that ship switched on.
     */
    public function promoByDefault(): bool
    {
        return true;
    }

    /** Name of the lucide-vue-next icon used by the sidebar and modules page. */
    public function icon(): string
    {
        return match ($this) {
            self::Transactions => 'ReceiptText',
            self::Reports => 'ChartPie',
            self::Bills => 'Receipt',
            self::Investments => 'TrendingUp',
            self::Portfolio => 'Wallet',
            self::AiAssistant => 'Sparkles',
            self::TelegramBot => 'Bot',
        };
    }

    /**
     * Features the user can actually switch on and off.
     *
     * @return array<int, self>
     */
    public static function toggleable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $feature): bool => ! $feature->isCore(),
        ));
    }
}
