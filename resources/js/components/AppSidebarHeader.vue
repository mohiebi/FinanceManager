<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, User } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type CurrencyOption = {
    label: string;
    value: string;
};

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
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    },
});
</script>

<template>
    <header
        class="flex h-[92px] shrink-0 items-center bg-[#454545] px-7 text-white shadow-sm transition-[width,height] ease-linear"
    >
        <div
            class="relative flex w-full flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-4">
                <img :src="logoGreen" alt="" class="h-9 w-8 shrink-0" />

                <div class="flex items-center gap-3 text-xl">
                    <span class="font-bold">CashPilot</span>
                    <span class="text-white/55">|</span>
                    <span>{{ pageTitle }}</span>
                </div>
            </div>

            <div class="hidden items-center gap-2 lg:flex">
                <button
                    class="relative grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white"
                    type="button"
                >
                    <Bell class="size-5" />
                    <span
                        class="absolute -top-2 -left-2 rounded-full bg-[#02cd86] px-1.5 py-0.5 text-[10px] leading-none font-bold text-[#2d2d2d]"
                    >
                        12
                    </span>
                    <span class="sr-only">{{
                        t('navigation.notifications')
                    }}</span>
                </button>
                <button
                    class="grid size-9 place-items-center rounded-md bg-[#2d2d2d] text-white"
                    type="button"
                >
                    <User class="size-5" />
                    <span class="sr-only">{{ t('navigation.account') }}</span>
                </button>
            </div>

            <div
                v-if="hasCurrencySelector"
                class="flex items-center gap-4 lg:absolute lg:left-1/2 lg:-translate-x-1/2"
            >
                <Label
                    for="layout_display_currency"
                    class="text-[22px] font-normal text-white"
                >
                    {{ t('finance.fields.currency') }}
                </Label>
                <Select
                    v-model="selectedCurrency"
                    :disabled="currencies.length === 0"
                >
                    <SelectTrigger
                        id="layout_display_currency"
                        class="h-10 min-w-36 rounded-md border-0 bg-[#2d2d2d] px-5 text-base text-white shadow-none disabled:opacity-100"
                    >
                        <SelectValue
                            :placeholder="t('finance.filters.select_currency')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="currency in currencies"
                            :key="currency.value"
                            :value="currency.value"
                        >
                            {{ currency.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </header>
</template>
