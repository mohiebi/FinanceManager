export type FeatureKey =
    | 'transactions'
    | 'categories'
    | 'reports'
    | 'bills'
    | 'investments'
    | 'portfolio';

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
    may_use: boolean;
    /** Non-core modules that must be on for this one to work. */
    requires: string[];
    /** Currently-enabled modules that switching this off would also switch off. */
    disables: string[];
};

export type CoreModuleCard = {
    key: FeatureKey;
    label: string;
    description: string;
    icon: string;
};
