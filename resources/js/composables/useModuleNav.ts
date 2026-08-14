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

export type ModuleNavItem = NavItem & {
    key: string;
    state: ModuleNavState;
    tier: ModuleState['tier'];
};

export type UseModuleNavReturn = {
    navItems: ComputedRef<ModuleNavItem[]>;
};

/**
 * The canonical primary navigation, shared by the sidebar and the mobile bottom
 * bar so the two can't drift apart.
 */
export function useModuleNav(): UseModuleNavReturn {
    const { navigationName } = useNavigationNaming();
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
            },
            {
                key: 'transactions',
                feature: null,
                title: navigationName('navigation.transactions'),
                href: transactionsIndex(),
                icon: ReceiptText,
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
            },
            {
                key: 'portfolio',
                feature: 'portfolio',
                title: navigationName('navigation.portfolio'),
                href: portfolio(),
                icon: Wallet,
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
            },
            {
                key: 'bills',
                feature: 'bills',
                title: navigationName('navigation.bills'),
                href: billsIndex(),
                icon: Receipt,
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
            },
            {
                key: 'telegram_bot',
                feature: 'telegram_bot',
                title: navigationName('navigation.telegram_bot'),
                href: editTelegram(),
                icon: Bot,
            },
        ];

        return entries.flatMap<ModuleNavItem>((entry) => {
            if (entry.feature === null) {
                return [{ ...entry, state: 'enabled', tier: 'free' }];
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

            // Same "off, but still worth advertising" spot in the nav either
            // way — only the badge (and what it's inviting the user toward)
            // differs between a free toggle and a Pro purchase.
            return [
                {
                    ...entry,
                    href: editModules(),
                    state: state.may_use ? 'promo' : 'locked',
                    tier: state.tier,
                },
            ];
        });
    });

    return { navItems };
}
