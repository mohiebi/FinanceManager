<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Crown,
    Eye,
    EyeOff,
    Lock,
    Menu,
    Plus,
    Settings,
    ShieldCheck,
    User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import MilesPill from '@/components/MilesPill.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAmountMask } from '@/composables/useAmountMask';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useModuleNav } from '@/composables/useModuleNav';
import type { ModuleNavItem } from '@/composables/useModuleNav';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { dashboard as adminDashboard } from '@/routes/admin';
import { update as updateLocale } from '@/routes/locale';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        subtitle?: string | null;
    }>(),
    {
        breadcrumbs: () => [],
        subtitle: null,
    },
);

type CurrencyOption = { label: string; value: string };
type CurrencyPageProps = {
    currencies?: CurrencyOption[];
    selectedCurrency?: string;
};

const page = usePage();
const { t } = useI18n();
const { isCurrentUrl } = useCurrentUrl();
const { navGroups } = useModuleNav();
const { navigationName } = useNavigationNaming();
const { masked, toggle: toggleMask } = useAmountMask();

const isMenuOpen = ref(false);

const user = computed(() => page.props.auth.user);

function isActive(item: ModuleNavItem): boolean {
    // A promo item's href is the modules page — standing on it must not light
    // up every promo row at once. A locked item points at its own route now, so
    // it can be the current page like any other.
    return item.state !== 'promo' && isCurrentUrl(item.href);
}

/** Advisor keeps its gold accent here too — see AppSidebar.vue. */
function isAdvisor(item: ModuleNavItem): boolean {
    return item.key === 'advisor';
}

const pageTitle = computed(() => {
    const title = props.breadcrumbs.at(-1)?.title ?? 'Dashboard';

    return (
        {
            Dashboard: navigationName(
                'navigation.dashboard',
                'navigation.dashboard_subtitle',
            ),
            Reports: navigationName(
                'navigation.report',
                'navigation.report_subtitle',
            ),
            Report: navigationName(
                'navigation.report',
                'navigation.report_subtitle',
            ),
            Transactions: navigationName('navigation.transactions'),
            Transaction: navigationName('navigation.transactions'),
            Investments: navigationName(
                'navigation.investments',
                'navigation.investments_subtitle',
            ),
            Portfolio: navigationName('navigation.portfolio'),
            Goals: navigationName(
                'navigation.goals',
                'navigation.goals_subtitle',
            ),
            Budgets: navigationName(
                'navigation.budgets',
                'navigation.budgets_subtitle',
            ),
            Advisor: navigationName('navigation.advisor'),
            FlightLog: navigationName(
                'navigation.flight_log',
                'navigation.flight_log_subtitle',
            ),
            Miles: navigationName('navigation.miles'),
            Preferences: t('settings.preferences.title'),
            Settings: navigationName(
                'settings.title',
                'navigation.settings_subtitle',
            ),
        }[title] ?? title
    );
});

const currencyProps = computed(() => page.props as CurrencyPageProps);
const currencies = computed(() => currencyProps.value.currencies ?? []);
const hasCurrencySelector = computed(() => currencies.value.length > 0);

const selectedCurrency = computed({
    get: () => currencyProps.value.selectedCurrency ?? '',
    set: (value: string) => {
        if (!value || value === currencyProps.value.selectedCurrency) {
            return;
        }

        const currentUrl = new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        );
        currentUrl.searchParams.set('currency', value);
        router.get(
            `${currentUrl.pathname}${currentUrl.search}`,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    },
});

function changeCurrency(value: string) {
    selectedCurrency.value = value;
}

const locales = computed(() => page.props.locales ?? []);
const hasLocaleSelector = computed(() => locales.value.length > 1);
const selectedLocale = computed(() => page.props.locale);

function changeLocale(value: string) {
    if (!value || value === selectedLocale.value) {
        return;
    }

    router.post(
        updateLocale.url(),
        { locale: value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <header
        class="sticky top-0 z-40 flex min-h-[64px] shrink-0 items-center border-b border-white/7 bg-[#111111]/88 px-4 py-3 text-white backdrop-blur-md transition-[width,height] ease-linear lg:min-h-[72px] lg:px-7 lg:py-4"
    >
        <div
            class="mx-auto flex w-full max-w-[1440px] flex-col gap-2 lg:flex-row lg:items-center lg:justify-between lg:gap-4"
        >
            <div
                class="flex w-full items-center justify-between gap-2 lg:w-auto"
            >
                <div class="min-w-0">
                    <h1
                        class="truncate text-[17px] font-semibold tracking-[-0.015em] text-white lg:text-[19px]"
                    >
                        {{ pageTitle }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="mt-0.5 truncate text-xs text-[#989898]"
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 lg:hidden">
                    <MilesPill compact />
                    <NotificationBell />
                    <Link
                        :href="editProfile()"
                        class="grid size-9 cursor-pointer place-items-center rounded-[9px] bg-[#1a1a1a] text-white transition-colors duration-150 hover:bg-[#252525]"
                    >
                        <User class="size-[18px]" />
                        <span class="sr-only">{{
                            t('navigation.account')
                        }}</span>
                    </Link>

                    <!-- Hamburger menu -->
                    <Sheet v-model:open="isMenuOpen">
                        <SheetTrigger as-child>
                            <button
                                type="button"
                                class="grid size-9 cursor-pointer place-items-center rounded-[9px] bg-[#1a1a1a] text-white transition-colors duration-150 hover:bg-[#252525]"
                                :aria-label="t('navigation.primary')"
                            >
                                <Menu class="size-[18px]" />
                            </button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            class="w-72 border-l border-white/7 bg-[#0d0d0d] p-0 text-white"
                        >
                            <SheetTitle class="sr-only">{{
                                t('navigation.primary')
                            }}</SheetTitle>
                            <div
                                class="app-scroll-thin flex h-full flex-col gap-5 overflow-y-auto px-4 pt-6 pb-10"
                            >
                                <nav
                                    v-for="group in navGroups"
                                    :key="group.key"
                                    class="flex flex-col gap-1"
                                    :aria-label="group.label"
                                >
                                    <p
                                        class="px-2.5 pb-1 text-[10px] font-medium tracking-[0.14em] text-[#5a5a5a] uppercase"
                                    >
                                        {{ group.label }}
                                    </p>
                                    <Link
                                        v-for="item in group.items"
                                        :key="item.key"
                                        :href="item.href"
                                        class="flex items-center gap-2.5 rounded-[9px] px-2.5 py-2.5 text-sm transition-colors"
                                        :class="
                                            isAdvisor(item)
                                                ? 'border-s-2 border-s-[#d9c48f] bg-[#d9c48f]/10 font-medium text-white'
                                                : isActive(item)
                                                  ? 'bg-[#252525] font-medium text-white'
                                                  : item.state !== 'enabled'
                                                    ? 'text-[#686868] hover:bg-white/5'
                                                    : 'text-[#989898] hover:bg-white/5 hover:text-white'
                                        "
                                        @click="isMenuOpen = false"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="size-[15px] shrink-0"
                                        />
                                        <span class="min-w-0 flex-1 truncate">{{
                                            item.title
                                        }}</span>

                                        <span
                                            v-if="
                                                item.tier === 'pro' ||
                                                item.state !== 'enabled'
                                            "
                                            :class="[
                                                'ml-auto inline-flex shrink-0 items-center gap-1',
                                                isAdvisor(item)
                                                    ? 'advisor-mono rounded-[4px] border border-[#d9c48f]/35 px-[5px] py-[2px] text-[8.5px] tracking-[0.12em] text-[#d9c48f] uppercase'
                                                    : 'rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                                                isAdvisor(item)
                                                    ? ''
                                                    : item.tier === 'pro'
                                                      ? 'bg-[#6c4ee9]/15 text-[#a89bf3]'
                                                      : 'bg-[#02cd86]/13 text-[#02cd86]',
                                            ]"
                                        >
                                            <Crown
                                                v-if="
                                                    item.tier === 'pro' &&
                                                    !isAdvisor(item)
                                                "
                                                class="size-2.5"
                                                aria-hidden="true"
                                            />
                                            <Lock
                                                v-else-if="
                                                    item.state === 'locked' &&
                                                    !isAdvisor(item)
                                                "
                                                class="size-2.5"
                                                aria-hidden="true"
                                            />
                                            <Plus
                                                v-else-if="
                                                    item.state === 'promo' &&
                                                    !isAdvisor(item)
                                                "
                                                class="size-2.5"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(`modules.tiers.${item.tier}`)
                                            }}
                                        </span>
                                    </Link>
                                </nav>

                                <div
                                    class="mt-auto flex flex-col gap-1 border-t border-white/7 pt-4"
                                >
                                    <Link
                                        v-if="page.props.auth.isAdmin"
                                        data-mobile-sidebar-admin
                                        :href="adminDashboard()"
                                        :aria-current="
                                            isCurrentUrl(adminDashboard())
                                                ? 'page'
                                                : undefined
                                        "
                                        class="flex items-center gap-2.5 rounded-[9px] px-2.5 py-2.5 text-sm font-medium transition-colors"
                                        :class="
                                            isCurrentUrl(adminDashboard())
                                                ? 'bg-[#252525] text-white'
                                                : 'text-[#989898] hover:bg-white/5 hover:text-white'
                                        "
                                        @click="isMenuOpen = false"
                                    >
                                        <ShieldCheck
                                            class="size-[15px] shrink-0"
                                        />
                                        <span class="min-w-0 truncate">
                                            {{
                                                navigationName(
                                                    'settings.navigation.admin',
                                                    'settings.navigation.admin_subtitle',
                                                )
                                            }}
                                        </span>
                                    </Link>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-2.5 rounded-[9px] px-2.5 py-2.5 text-sm font-medium text-[#989898] transition-colors hover:bg-white/5 hover:text-white"
                                            >
                                                <Settings
                                                    class="size-[15px] shrink-0"
                                                />
                                                <span class="min-w-0 truncate">
                                                    {{
                                                        navigationName(
                                                            'settings.title',
                                                            'navigation.settings_subtitle',
                                                        )
                                                    }}
                                                </span>
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            class="w-64"
                                            side="top"
                                            align="start"
                                            :side-offset="8"
                                        >
                                            <UserMenuContent :user="user" />
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <div
                class="flex flex-wrap items-center gap-2 lg:flex-nowrap lg:gap-2.5"
            >
                <div class="hidden lg:block">
                    <MilesPill />
                </div>
                <!-- Currency switcher -->
                <div
                    v-if="hasCurrencySelector"
                    class="flex items-center gap-0.5 rounded-[9px] border border-white/8 bg-[#1a1a1a] p-[3px]"
                >
                    <button
                        v-for="c in currencies"
                        :key="c.value"
                        type="button"
                        class="cursor-pointer rounded-[7px] px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                        :class="
                            selectedCurrency === c.value
                                ? 'bg-[#02cd86] text-[#101010]'
                                : 'text-[#989898] hover:text-white'
                        "
                        @click="changeCurrency(c.value)"
                    >
                        {{ c.label }}
                    </button>
                </div>

                <!-- Language switcher -->
                <div
                    v-if="hasLocaleSelector"
                    class="flex items-center gap-0.5 rounded-[9px] border border-white/8 bg-[#1a1a1a] p-[3px]"
                >
                    <button
                        v-for="l in locales"
                        :key="l"
                        type="button"
                        class="cursor-pointer rounded-[7px] px-2.5 py-1.5 text-xs font-medium whitespace-nowrap transition-colors"
                        :class="
                            selectedLocale === l
                                ? 'bg-[#02cd86] text-[#101010]'
                                : 'text-[#989898] hover:text-white'
                        "
                        @click="changeLocale(l)"
                    >
                        {{ l.toUpperCase() }}
                    </button>
                </div>

                <!-- Amount mask -->
                <button
                    type="button"
                    class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-[9px] border border-white/8 bg-[#1a1a1a] px-3 py-[9px] text-xs font-medium text-[#989898] transition-colors hover:bg-[#252525] hover:text-white"
                    @click="toggleMask"
                >
                    <EyeOff v-if="masked" class="size-[13px]" />
                    <Eye v-else class="size-[13px]" />
                    {{
                        masked
                            ? t('common.show_amounts')
                            : t('common.hide_amounts')
                    }}
                </button>

                <div class="hidden shrink-0 items-center gap-2 lg:flex">
                    <NotificationBell />
                    <Link
                        :href="editProfile()"
                        class="grid size-9 cursor-pointer place-items-center rounded-[9px] bg-[#1a1a1a] text-white transition-colors duration-150 hover:bg-[#252525]"
                    >
                        <User class="size-[17px]" />
                        <span class="sr-only">{{
                            t('navigation.account')
                        }}</span>
                    </Link>
                </div>
            </div>
        </div>
    </header>
</template>
