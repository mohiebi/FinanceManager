<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Lock, Menu, Plus, Settings, ShieldCheck, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useModuleNav } from '@/composables/useModuleNav';
import type { ModuleNavItem } from '@/composables/useModuleNav';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';
import logoGreen from '../../img/Logo-green.svg';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
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
const { navItems } = useModuleNav();

const isMenuOpen = ref(false);

const user = computed(() => page.props.auth.user);

function isActive(item: ModuleNavItem): boolean {
    return item.state === 'enabled' && isCurrentUrl(item.href);
}

const pageTitle = computed(() => {
    const title = props.breadcrumbs.at(-1)?.title ?? 'Dashboard';

    return (
        {
            Dashboard: t('finance.dashboard.title'),
            Reports: t('finance.reports.title'),
            Report: t('finance.reports.title'),
            Transactions: t('finance.transactions.title'),
            Transaction: t('finance.transactions.title'),
            Investments: t('finance.investments.title'),
            Portfolio: t('finance.portfolio.title'),
            Preferences: t('settings.preferences.title'),
            Settings: t('settings.title'),
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
</script>

<template>
    <header
        class="sticky top-0 z-40 flex min-h-[72px] shrink-0 items-center bg-[#454545] px-4 py-3 text-white shadow-sm transition-[width,height] ease-linear lg:static lg:min-h-[92px] lg:px-7 lg:py-6"
    >
        <div
            class="flex w-full flex-col gap-2 lg:flex-row lg:items-center lg:justify-between lg:gap-0"
        >
            <div
                class="flex w-full items-center justify-between gap-2 lg:contents"
            >
                <div class="flex min-w-0 items-center gap-2.5 lg:gap-3">
                    <img
                        :src="logoGreen"
                        alt=""
                        class="h-10 w-10 shrink-0 lg:h-12 lg:w-12"
                    />
                    <div
                        class="flex min-w-0 items-center gap-2 text-[15px] font-normal lg:gap-3 lg:text-xl"
                    >
                        <span class="shrink-0 font-bold">CashPilot</span>
                        <span class="shrink-0 text-white/55">|</span>
                        <span class="truncate">{{ pageTitle }}</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1.5 lg:hidden">
                    <NotificationBell />
                    <Link
                        :href="editProfile()"
                        class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
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
                                class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
                                :aria-label="t('navigation.primary')"
                            >
                                <Menu class="size-[18px]" />
                            </button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            class="w-72 border-l border-white/10 bg-[#353535] p-0 text-white"
                        >
                            <SheetTitle class="sr-only">{{
                                t('navigation.primary')
                            }}</SheetTitle>
                            <div
                                class="flex h-full flex-col justify-between px-5 pt-6 pb-10"
                            >
                                <nav
                                    class="flex flex-col gap-3"
                                    :aria-label="t('navigation.primary')"
                                >
                                    <Link
                                        v-for="item in navItems"
                                        :key="item.key"
                                        :href="item.href"
                                        class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors duration-150"
                                        :class="
                                            isActive(item)
                                                ? 'bg-[#454545] text-[#02cd86]'
                                                : item.state !== 'enabled'
                                                  ? 'text-white/35 hover:bg-[#454545] hover:text-white/60'
                                                  : 'text-white/70 hover:bg-[#454545] hover:text-white'
                                        "
                                        @click="isMenuOpen = false"
                                    >
                                        <span class="relative shrink-0">
                                            <component
                                                :is="item.icon"
                                                class="size-[18px] shrink-0"
                                            />
                                            <Plus
                                                v-if="item.state === 'promo'"
                                                class="rtl:-right-auto absolute -top-1 -right-1 size-3 rounded-full bg-[#02cd86] text-[#353535] rtl:-left-1"
                                            />
                                            <!-- Pro, not bought yet — a lock
                                                 rather than the free "+", so it
                                                 doesn't promise a tap turns it on. -->
                                            <Lock
                                                v-else-if="
                                                    item.state === 'locked'
                                                "
                                                class="rtl:-right-auto absolute -top-1 -right-1 size-3 rounded-full bg-[#6C4EE9] p-px text-white rtl:-left-1"
                                            />
                                        </span>
                                        <span
                                            class="flex min-w-0 flex-col items-center"
                                        >
                                            <span class="truncate">{{
                                                item.subtitle ?? item.title
                                            }}</span>
                                            <!-- The plain functional name, kept
                                                 as a quiet aside now that the
                                                 flight name leads. -->
                                            <span
                                                v-if="item.subtitle"
                                                class="truncate text-[11px] font-normal text-white/35 lowercase"
                                            >
                                                {{ item.title }}
                                            </span>
                                        </span>

                                        <!-- Off is never "you have to pay" —
                                             it just is not switched on yet. -->
                                        <span
                                            v-if="item.state !== 'enabled'"
                                            :class="[
                                                'ml-auto shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium',
                                                item.state === 'locked'
                                                    ? 'bg-[#6C4EE9]/15 text-[#a89bf3]'
                                                    : 'bg-[#02CD86]/10 text-[#02CD86]',
                                            ]"
                                        >
                                            {{
                                                t(
                                                    `modules.tiers.${item.state === 'locked' ? 'pro' : 'free'}`,
                                                )
                                            }}
                                        </span>
                                    </Link>
                                </nav>

                                <div class="flex flex-col gap-3">
                                    <Link
                                        v-if="page.props.auth.isAdmin"
                                        data-mobile-sidebar-admin
                                        :href="adminDashboard()"
                                        :aria-current="
                                            isCurrentUrl(adminDashboard())
                                                ? 'page'
                                                : undefined
                                        "
                                        class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none"
                                        :class="
                                            isCurrentUrl(adminDashboard())
                                                ? 'bg-[#454545] text-[#02cd86]'
                                                : 'text-white/70 hover:bg-[#454545] hover:text-white'
                                        "
                                        @click="isMenuOpen = false"
                                    >
                                        <ShieldCheck
                                            class="size-[18px] shrink-0"
                                        />
                                        <span
                                            class="flex min-w-0 flex-col items-center"
                                        >
                                            <span class="truncate">{{
                                                t(
                                                    'settings.navigation.admin_subtitle',
                                                )
                                            }}</span>
                                            <span
                                                class="truncate text-[11px] font-normal text-white/35 lowercase"
                                            >
                                                {{
                                                    t(
                                                        'settings.navigation.admin',
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </Link>

                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-white/70 transition-colors duration-150 hover:bg-[#454545] hover:text-white focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none"
                                            >
                                                <Settings
                                                    class="size-[18px] shrink-0"
                                                />
                                                <span
                                                    class="flex min-w-0 flex-col items-center"
                                                >
                                                    <span class="truncate">{{
                                                        t(
                                                            'navigation.settings_subtitle',
                                                        )
                                                    }}</span>
                                                    <span
                                                        class="truncate text-[11px] font-normal text-white/35 lowercase"
                                                    >
                                                        {{
                                                            t('settings.title')
                                                        }}
                                                    </span>
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

            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <NotificationBell />
                <Link
                    :href="editProfile()"
                    class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
                >
                    <User class="size-5" />
                    <span class="sr-only">{{ t('navigation.account') }}</span>
                </Link>
            </div>

            <div
                v-if="hasCurrencySelector"
                class="ml-[44px] flex items-center gap-1 lg:absolute lg:left-1/2 lg:ml-0 lg:-translate-x-1/2"
            >
                <button
                    v-for="c in currencies"
                    :key="c.value"
                    type="button"
                    :class="[
                        'cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition-all duration-150',
                        selectedCurrency === c.value
                            ? 'bg-[#02CD86]/10 text-[#02CD86] ring-1 ring-[#02CD86]/30'
                            : 'text-[#989898] ring-1 ring-white/10 hover:bg-white/10 hover:text-white',
                    ]"
                    @click="changeCurrency(c.value)"
                >
                    {{ c.label }}
                </button>
            </div>
        </div>
    </header>
</template>
