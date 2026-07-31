export type FeatureKey =
    | 'transactions'
    | 'reports'
    | 'bills'
    | 'investments'
    | 'portfolio'
    | 'gamification'
    | 'ai_assistant'
    | 'telegram_bot'
    | 'vault';

/** A feature's resolved state, as shared on every authenticated page. */
export type ModuleState = {
    enabled: boolean;
    show_promo: boolean;
    core: boolean;
};

export type FeatureMap = Record<FeatureKey, ModuleState>;

/** The richer shape the modules settings page renders. */
export type ModuleCard = {
    key: FeatureKey;
    label: string;
    description: string;
    icon: string;
    tier: 'free' | 'pro';
    enabled: boolean;
    show_promo: boolean;
    /** False for modules with no sidebar entry, which cannot advertise themselves. */
    in_nav: boolean;
    may_use: boolean;
    /** Non-core modules that must be on for this one to work. */
    requires: string[];
    /** Currently-enabled modules that switching this off would also switch off. */
    disables: string[];
    /**
     * Set when the module is switched somewhere else — it renders as a link card
     * rather than a toggle, because turning it on re-keys the user's data.
     */
    manage_url: string | null;
};

export type CoreModuleCard = {
    key: FeatureKey;
    label: string;
    description: string;
    icon: string;
};
