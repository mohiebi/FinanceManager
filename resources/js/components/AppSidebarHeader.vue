<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { User } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import NotificationBell from '@/components/NotificationBell.vue';
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
        class="flex min-h-[72px] shrink-0 items-center bg-[#454545] px-4 text-white shadow-sm transition-[width,height] ease-linear lg:min-h-[92px] lg:px-7"
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
