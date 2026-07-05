<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartPie,
    LayoutGrid,
    Receipt,
    ReceiptText,
    Settings,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, portfolio, report } from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { index as investmentsIndex } from '@/routes/investments';
import { index as transactionsIndex } from '@/routes/transactions';

const { t } = useI18n();
const { isCurrentUrl } = useCurrentUrl();

const page = usePage();
const user = computed(() => page.props.auth.user);

const navItems = computed(() => [
    { title: t('navigation.dashboard'), href: dashboard(), icon: LayoutGrid },
    {
        title: t('navigation.transactions'),
        href: transactionsIndex(),
        icon: ReceiptText,
    },
    { title: t('navigation.report'), href: report(), icon: ChartPie },
    {
        title: t('navigation.investments'),
        href: investmentsIndex(),
        icon: TrendingUp,
    },
    { title: t('navigation.portfolio'), href: portfolio(), icon: Wallet },
    { title: t('navigation.bills'), href: billsIndex(), icon: Receipt },
]);
</script>

<template>
    <!-- Visible only below lg breakpoint -->
    <nav
        :aria-label="t('navigation.mobile')"
        class="fixed inset-x-0 bottom-0 z-[200] flex h-16 items-stretch border-t border-white/10 bg-[#353535] lg:hidden"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)"
    >
        <!-- Main nav items -->
        <Link
            v-for="item in navItems"
            :key="item.title"
            :href="item.href"
            :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
            class="relative flex flex-1 flex-col items-center justify-center gap-1 transition-colors duration-150"
            :class="
                isCurrentUrl(item.href)
                    ? 'text-[#02cd86]'
                    : 'text-white/45 hover:text-white/75'
            "
        >
            <!-- Active indicator bar -->
            <span
                v-if="isCurrentUrl(item.href)"
                class="absolute inset-x-3 top-0 h-[2px] rounded-b-full bg-[#02cd86]"
            />
            <component :is="item.icon" class="size-[22px] shrink-0" />
            <span
                class="max-w-full truncate text-[9px] leading-tight font-medium tracking-wide"
            >
                {{ item.title }}
            </span>
        </Link>

        <!-- Settings dropdown -->
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    :title="t('settings.title')"
                    class="flex flex-1 flex-col items-center justify-center gap-1 text-white/45 transition-colors duration-150 hover:text-white/75 focus-visible:outline-none"
                >
                    <Settings class="size-[22px] shrink-0" />
                    <span
                        class="max-w-full truncate text-[9px] leading-tight font-medium tracking-wide"
                    >
                        {{ t('settings.title') }}
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="w-64"
                side="top"
                align="end"
                :side-offset="8"
            >
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </nav>
</template>
