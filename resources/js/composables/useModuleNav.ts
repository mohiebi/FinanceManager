import { usePage } from '@inertiajs/vue3';
import {
    Bot,
    BrainCircuit,
    ChartPie,
    LayoutGrid,
    Receipt,
    ReceiptText,
    Sparkles,
    Target,
    TrendingUp,
    Trophy,
    Wallet,
} from 'lucide-vue-next';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { dashboard, goals, portfolio, report } from '@/routes';
import { index as advisorIndex } from '@/routes/advisor';
import { edit as editAiConnections } from '@/routes/ai-connections';
import { index as billsIndex } from '@/routes/bills';
import { index as budgetsIndex } from '@/routes/budgets';
import { index as investmentsIndex } from '@/routes/investments';
import { edit as editModules } from '@/routes/modules';
import { edit as editTelegram } from '@/routes/telegram';
import { index as transactionsIndex } from '@/routes/transactions';
import type { FeatureKey, ModuleState } from '@/types/features';
import type { NavItem } from '@/types/navigation';

/**
 * `enabled` renders normally. `promo` renders greyed with a "+" — free, just
 * switched off. `locked` renders greyed with a lock — a Pro module the user
 * has not bought, so there is nothing to switch on yet. Modules the user hid
 * are dropped from the list entirely, so there is no fourth state to render.
 */
export type ModuleNavState = 'enabled' | 'promo' | 'locked';

/** Visual grouping only — has no bearing on feature gating. */
export type ModuleNavGroup = 'money' | 'plan' | 'grow' | 'system';

export type ModuleNavItem = NavItem & {
    key: string;
    state: ModuleNavState;
    tier: ModuleState['tier'];
    group: ModuleNavGroup;
};

export type ModuleNavGroupEntry = {
    key: ModuleNavGroup;
    label: string;
    items: ModuleNavItem[];
};

export type UseModuleNavReturn = {
    navItems: ComputedRef<ModuleNavItem[]>;
    navGroups: ComputedRef<ModuleNavGroupEntry[]>;
};

/**
 * The canonical primary navigation, shared by the sidebar and the mobile bottom
 * bar so the two can't drift apart.
 */
export function useModuleNav(): UseModuleNavReturn {
    const { navigationName } = useNavigationNaming();
    const { t } = useI18n();
    const page = usePage();

    const navItems = computed<ModuleNavItem[]>(() => {
        const features = page.props.features;

        // `feature: null` means always on — it isn't a module.
        const entries: {
            key: string;
            feature: FeatureKey | null;
            title: string;
            href: NavItem['href'];
            icon: NavItem['icon'];
            group: ModuleNavGroup;
        }[] = [
            {
                key: 'dashboard',
                feature: null,
                title: navigationName(
                    'navigation.dashboard',
                    'navigation.dashboard_subtitle',
                ),
                href: dashboard(),
                icon: LayoutGrid,
                group: 'money',
            },
            {
                key: 'transactions',
                feature: null,
                title: navigationName('navigation.transactions'),
                href: transactionsIndex(),
                icon: ReceiptText,
                group: 'money',
            },
            {
                key: 'reports',
                feature: null,
                title: navigationName(
                    'navigation.report',
                    'navigation.report_subtitle',
                ),
                href: report(),
                icon: ChartPie,
                group: 'money',
            },
            {
                key: 'budgets',
                feature: 'budgets',
                title: navigationName(
                    'navigation.budgets',
                    'navigation.budgets_subtitle',
                ),
                href: budgetsIndex(),
                icon: Target,
                group: 'plan',
            },
            {
                key: 'bills',
                feature: 'bills',
                title: navigationName('navigation.bills'),
                href: billsIndex(),
                icon: Receipt,
                group: 'plan',
            },
            {
                key: 'goals',
                feature: 'goals',
                title: navigationName(
                    'navigation.goals',
                    'navigation.goals_subtitle',
                ),
                href: goals(),
                icon: Trophy,
                group: 'plan',
            },
            {
                key: 'investments',
                feature: 'investments',
                title: navigationName(
                    'navigation.investments',
                    'navigation.investments_subtitle',
                ),
                href: investmentsIndex(),
                icon: TrendingUp,
                group: 'grow',
            },
            {
                key: 'portfolio',
                feature: 'portfolio',
                title: navigationName('navigation.portfolio'),
                href: portfolio(),
                icon: Wallet,
                group: 'grow',
            },
            {
                key: 'advisor',
                feature: 'advisor',
                title: navigationName(
                    'navigation.advisor',
                    'navigation.advisor_subtitle',
                ),
                href: advisorIndex(),
                icon: BrainCircuit,
                group: 'grow',
            },
            {
                key: 'ai_assistant',
                feature: 'ai_assistant',
                title: navigationName(
                    'navigation.ai_assistant',
                    'navigation.ai_assistant_subtitle',
                ),
                href: editAiConnections(),
                icon: Sparkles,
                group: 'system',
            },
            {
                key: 'telegram_bot',
                feature: 'telegram_bot',
                title: navigationName('navigation.telegram_bot'),
                href: editTelegram(),
                icon: Bot,
                group: 'system',
            },
        ];

        return entries.flatMap<ModuleNavItem>((entry) => {
            if (entry.feature === null) {
                return [{ ...entry, state: 'enabled', tier: 'free' as const }];
            }

            const state = features?.[entry.feature];

            // Missing prop means an unauthenticated or partial page — fall back to
            // showing the item rather than rendering an empty menu.
            if (state === undefined || state.enabled) {
                return [
                    {
                        ...entry,
                        state: 'enabled',
                        tier: state?.tier ?? 'free',
                    },
                ];
            }

            if (!state.show_promo) {
                return [];
            }

            /*
             * Same "off, but still worth advertising" spot in the nav either
             * way — what differs is where the user can act on it.
             *
             * A module the plan does not cover sends them to the feature's own
             * route, because that is where its paywall lives and the paywall is
             * the sales page. Pointing at the modules settings page instead put
             * a locked row and a switch that refuses to move in front of
             * somebody who wanted to buy the thing.
             *
             * A module they own but switched off still points at the modules
             * page, because that is genuinely where the switch is.
             */
            const locked = !state.may_use;

            return [
                {
                    ...entry,
                    href: locked ? entry.href : editModules(),
                    state: locked ? 'locked' : 'promo',
                    tier: state.tier,
                },
            ];
        });
    });

    const navGroups = computed<ModuleNavGroupEntry[]>(() => {
        const order: ModuleNavGroup[] = ['money', 'plan', 'grow', 'system'];
        const labels: Record<ModuleNavGroup, string> = {
            money: t('navigation.nav_group_money'),
            plan: t('navigation.nav_group_plan'),
            grow: t('navigation.nav_group_grow'),
            system: t('navigation.nav_group_system'),
        };

        return order
            .map((key) => ({
                key,
                label: labels[key],
                items: navItems.value.filter((item) => item.group === key),
            }))
            .filter((group) => group.items.length > 0);
    });

    return { navItems, navGroups };
}
