import { usePage } from '@inertiajs/vue3';
import {
    Bot,
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
import { dashboard, goals, portfolio, report } from '@/routes';
import { edit as editAiConnections } from '@/routes/ai-connections';
import { index as billsIndex } from '@/routes/bills';
import { index as budgetsIndex } from '@/routes/budgets';
import { index as investmentsIndex } from '@/routes/investments';
import { edit as editModules } from '@/routes/modules';
import { edit as editTelegram } from '@/routes/telegram';
import { index as transactionsIndex } from '@/routes/transactions';
import type { FeatureKey } from '@/types/features';
import type { NavItem } from '@/types/navigation';

/**
 * `enabled` renders normally; `promo` renders greyed with a "+". Modules the
 * user hid are dropped from the list entirely, so there
 * is no third state to render.
 */
export type ModuleNavState = 'enabled' | 'promo';

export type ModuleNavItem = NavItem & {
    key: string;
    state: ModuleNavState;
};

export type UseModuleNavReturn = {
    navItems: ComputedRef<ModuleNavItem[]>;
};

/**
 * The canonical primary navigation, shared by the sidebar and the mobile bottom
 * bar so the two can't drift apart.
 */
export function useModuleNav(): UseModuleNavReturn {
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
        }[] = [
            {
                key: 'dashboard',
                feature: null,
                title: t('navigation.dashboard'),
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                key: 'transactions',
                feature: null,
                title: t('navigation.transactions'),
                href: transactionsIndex(),
                icon: ReceiptText,
            },
            {
                key: 'reports',
                feature: null,
                title: t('navigation.report'),
                href: report(),
                icon: ChartPie,
            },
            {
                key: 'investments',
                feature: 'investments',
                title: t('navigation.investments'),
                href: investmentsIndex(),
                icon: TrendingUp,
            },
            {
                key: 'portfolio',
                feature: 'portfolio',
                title: t('navigation.portfolio'),
                href: portfolio(),
                icon: Wallet,
            },
            {
                key: 'goals',
                feature: 'goals',
                title: t('navigation.goals'),
                href: goals(),
                icon: Trophy,
            },
            {
                key: 'bills',
                feature: 'bills',
                title: t('navigation.bills'),
                href: billsIndex(),
                icon: Receipt,
            },
            {
                key: 'budgets',
                feature: 'budgets',
                title: t('navigation.budgets'),
                href: budgetsIndex(),
                icon: Target,
            },
            {
                key: 'ai_assistant',
                feature: 'ai_assistant',
                title: t('navigation.ai_assistant'),
                href: editAiConnections(),
                icon: Sparkles,
            },
            {
                key: 'telegram_bot',
                feature: 'telegram_bot',
                title: t('navigation.telegram_bot'),
                href: editTelegram(),
                icon: Bot,
            },
        ];

        return entries.flatMap<ModuleNavItem>((entry) => {
            if (entry.feature === null) {
                return [{ ...entry, state: 'enabled' }];
            }

            const state = features?.[entry.feature];

            // Missing prop means an unauthenticated or partial page — fall back to
            // showing the item rather than rendering an empty menu.
            if (state === undefined || state.enabled) {
                return [{ ...entry, state: 'enabled' }];
            }

            if (!state.show_promo) {
                return [];
            }

            return [{ ...entry, href: editModules(), state: 'promo' }];
        });
    });

    return { navItems };
}
