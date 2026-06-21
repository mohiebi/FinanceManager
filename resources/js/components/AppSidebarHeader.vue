<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSidebar } from '@/components/ui/sidebar';
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
const { toggleSidebar } = useSidebar();

const pageTitle = computed(() => {
    const title = props.breadcrumbs.at(-1)?.title ?? 'Dashboard';
    return (
        {
            Dashboard:    t('finance.dashboard.title'),
            Reports:      t('finance.reports.title'),
            Report:       t('finance.reports.title'),
            Transactions: t('finance.transactions.title'),
            Transaction:  t('finance.transactions.title'),
            Investments:  t('finance.investments.title'),
            Portfolio:    t('finance.portfolio.title'),
            Preferences:  t('settings.preferences.title'),
            Settings:     t('settings.title'),
        }[title] ?? title
    );
});

const currencyProps = computed(() => page.props as CurrencyPageProps);
const currencies    = computed(() => currencyProps.value.currencies ?? []);
const hasCurrencySelector = computed(() => currencies.value.length > 0);

const selectedCurrency = computed({
    get: () => currencyProps.value.selectedCurrency ?? '',
    set: (value: string) => {
        if (!value || value === currencyProps.value.selectedCurrency) return;
        const currentUrl = new URL(
            page.url,
            typeof window !== 'undefined' ? window.location.origin : 'http://localhost',
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
        class="flex min-h-[72px] shrink-0 items-center bg-[#454545] px-4 text-white shadow-sm transition-[width,height] ease-linear lg:min-h-[92px] lg:px-7"
    >
        <div class="flex w-full flex-col gap-2 lg:flex-row lg:items-center lg:justify-between lg:gap-0">

            <!-- ── Row 1 (mobile) / full row (desktop) ─────────────── -->
            <!-- On lg+ `lg:contents` dissolves this wrapper so children
                 participate directly in the parent flex-row             -->
            <div class="flex w-full items-center justify-between gap-2 lg:contents">

                <!-- Logo + page title -->
                <div class="flex min-w-0 items-center gap-2.5 lg:gap-3">
                    <img :src="logoGreen" alt="" class="h-8 w-auto shrink-0 lg:h-9" />
                    <div class="flex min-w-0 items-center gap-2 text-[15px] font-normal lg:gap-3 lg:text-xl">
                        <span class="shrink-0 font-bold">CashPilot</span>
                        <span class="shrink-0 text-white/55">|</span>
                        <span class="truncate">{{ pageTitle }}</span>
                    </div>
                </div>

                <!-- Mobile-only right controls -->
                <div class="flex shrink-0 items-center gap-1.5 lg:hidden">
                    <!-- Profile shortcut -->
                    <Link
                        :href="editProfile()"
                        class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
                    >
                        <User class="size-[18px]" />
                        <span class="sr-only">{{ t('navigation.account') }}</span>
                    </Link>

                    <!-- Hamburger → opens sidebar Sheet with full nav -->
                    <button
                        type="button"
                        :title="t('navigation.menu_toggle')"
                        class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#02cd86]"
                        @click="toggleSidebar"
                    >
                        <Menu class="size-[18px]" />
                    </button>
                </div>
            </div>

            <!-- ── Desktop-only profile icon (far right) ──────────── -->
            <div class="hidden shrink-0 items-center gap-2 lg:flex">
                <Link
                    :href="editProfile()"
                    class="grid size-9 cursor-pointer place-items-center rounded-md bg-[#2d2d2d] text-white transition-colors duration-150 hover:bg-[#02cd86] hover:text-[#1a1a1a]"
                >
                    <User class="size-5" />
                    <span class="sr-only">{{ t('navigation.account') }}</span>
                </Link>
            </div>

            <!-- ── Currency pills ──────────────────────────────────── -->
            <!-- Row 2 on mobile; absolute-centred on desktop          -->
            <div
                v-if="hasCurrencySelector"
                class="flex items-center gap-1 ml-[44px] lg:ml-0 lg:absolute lg:left-1/2 lg:-translate-x-1/2"
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
