<?php

namespace App\Enums;

use App\Models\User;
use App\Support\FeatureSet;

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
    case Budgets = 'budgets';
    case Investments = 'investments';
    case Portfolio = 'portfolio';
    case Goals = 'goals';
    case Gamification = 'gamification';
    case AiAssistant = 'ai_assistant';
    case Advisor = 'advisor';
    case TelegramBot = 'telegram_bot';
    case Vault = 'vault';

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
            self::Budgets => 'Flight plan',
            self::Investments => 'Investments',
            self::Portfolio => 'Portfolio',
            self::Goals => 'Savings goals',
            self::Gamification => 'Flight log',
            self::AiAssistant => 'AI Assistant',
            self::Advisor => 'AI Portfolio Advisor',
            self::TelegramBot => 'Telegram Bot',
            self::Vault => 'Private vault',
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
            self::Advisor => FeatureTier::Pro,
            default => FeatureTier::Free,
        };
    }

    /**
     * Whether a plan carrying the given Pro entitlement may use this feature.
     *
     * Free features are usable regardless — `$isPro` only ever gates a paid
     * tier. The single source both {@see User::mayUse()} and
     * {@see FeatureSet::toArray()} defer to, so the nav and the
     * modules page can never disagree about who is entitled to what.
     */
    public function mayUseWithPro(bool $isPro): bool
    {
        return $this->tier() === FeatureTier::Free || $isPro;
    }

    /**
     * Features that must be enabled for this one to work.
     *
     * @return array<int, self>
     */
    public function requires(): array
    {
        return match ($this) {
            // Goals depend on Investments rather than on Portfolio: progress is
            // a ratio of holdings, which is what Investments records. The
            // portfolio's net worth and P&L are a different question entirely,
            // and someone tracking grams of gold should not have to switch it on.
            self::Portfolio, self::Goals => [self::Investments],
            self::Bills, self::Reports, self::Gamification, self::Budgets => [self::Transactions],
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
            // Telegram and the AI assistant are the only two that cannot be rescued:
            // a cron job or an MCP call has no browser in the loop, so there is
            // nowhere to ask for a passphrase and nothing that can decrypt.
            //
            // Bills and Portfolio used to be here too. Both now have a client-side
            // path — bills are sealed in the browser before they are submitted, and
            // the portfolio breakdown is computed there from decrypted holdings — so
            // neither needs a readable server any more.
            self::Vault => [self::TelegramBot, self::AiAssistant],
            default => [],
        };
    }

    /**
     * A settings route that owns this feature's on/off switch, if the generic
     * modules endpoint must not touch it.
     *
     * The vault cannot be flipped by a plain PATCH: arming it requires the browser
     * to wrap the data key first, and a server-side toggle would destroy the only
     * copy of that key with nothing wrapped in its place.
     */
    public function managedRoute(): ?string
    {
        return match ($this) {
            self::Vault => 'security.edit',
            default => null,
        };
    }

    public function isSelfManaged(): bool
    {
        return $this->managedRoute() !== null;
    }

    /**
     * Whether this module owns an entry in the primary navigation.
     *
     * Not every module is a page. The flight log renders on the dashboard and
     * the vault is a security setting, so neither has anywhere for a sidebar
     * item to point — which also means the "hide from menu" promo state is
     * meaningless for them and must not be offered.
     *
     * Kept in step with the entries in resources/js/composables/useModuleNav.ts.
     */
    public function appearsInNav(): bool
    {
        return match ($this) {
            self::Gamification, self::Vault => false,
            default => true,
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

    /**
     * Whether a user who has never touched the modules page has this switched on.
     *
     * Core features always are. The flight log also is, unlike every other
     * optional module: it has no page of its own to discover, so shipping it off
     * would mean nobody ever sees it. It stays switched off-able for the people
     * who find streaks patronising in a finance tool.
     */
    public function enabledByDefault(): bool
    {
        return $this->isCore() || $this === self::Gamification;
    }

    /**
     * Whether a disabled module advertises itself in the nav by default.
     *
     * True wherever there is a nav to advertise in — discovery is the whole point
     * of the promo state, and it matches the `show_promo` column default. Only
     * ever consulted while a feature is disabled, so it says nothing about
     * modules that ship switched on.
     */
    public function promoByDefault(): bool
    {
        return $this->appearsInNav();
    }

    /** Name of the lucide-vue-next icon used by the sidebar and modules page. */
    public function icon(): string
    {
        return match ($this) {
            self::Transactions => 'ReceiptText',
            self::Reports => 'ChartPie',
            self::Bills => 'Receipt',
            self::Budgets => 'Target',
            self::Investments => 'TrendingUp',
            self::Portfolio => 'Wallet',
            self::Goals => 'Trophy',
            self::Gamification => 'Plane',
            self::AiAssistant => 'Sparkles',
            self::Advisor => 'BrainCircuit',
            self::TelegramBot => 'Bot',
            self::Vault => 'ShieldCheck',
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

    /**
     * Modules the generic modules endpoint is allowed to switch.
     *
     * Self-managed features are listed on the modules page but only ever toggled
     * by the controller that owns their crypto.
     *
     * @return array<int, self>
     */
    public static function directlyToggleable(): array
    {
        return array_values(array_filter(
            self::toggleable(),
            fn (self $feature): bool => ! $feature->isSelfManaged(),
        ));
    }
}
