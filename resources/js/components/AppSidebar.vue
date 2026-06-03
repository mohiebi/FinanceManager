<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartPie,
    LayoutGrid,
    ReceiptText,
    Settings,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar, SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, portfolio, report } from '@/routes';
import { index as investmentsIndex } from '@/routes/investments';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Transactions', href: transactionsIndex(), icon: ReceiptText },
    { title: 'Report', href: report(), icon: ChartPie },
    { title: 'Investments', href: investmentsIndex(), icon: TrendingUp },
    { title: 'Portfolio', href: portfolio(), icon: Wallet },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

const iconBoxBase =
    'flex h-9 w-9 shrink-0 items-center justify-center rounded-md shadow-[0_2px_6px_rgba(0,0,0,0.35)] transition-colors';
const iconBoxDefault = `${iconBoxBase} bg-[#2d2d2d]`;
const iconBoxActive = `${iconBoxBase} bg-[#454545]`;
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-0 p-0">
        <div
            class="flex h-full w-full flex-col justify-between bg-[#353535] px-5 pb-11 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-0"
        >
            <div
                class="flex w-full flex-col gap-10 pt-6 group-data-[collapsible=icon]:items-center"
            >
                <!-- ── Menu toggle ─────────────────────────────────── -->
                <!-- SidebarTrigger handles the icon + toggle logic;
                     the outer div adds the "Menu toggle" text label. -->
                <div
                    class="flex w-full items-center gap-3 group-data-[collapsible=icon]:justify-center"
                >
                    <SidebarTrigger
                        :class="[
                            iconBoxDefault,
                            'text-white hover:bg-[#3a3a3a]',
                            '[&_svg]:size-[18px]',
                        ]"
                    />
                    <span
                        class="truncate text-sm font-medium text-white/60 group-data-[collapsible=icon]:hidden"
                    >
                        Menu toggle
                    </span>
                </div>

                <!-- ── Primary navigation ──────────────────────────── -->
                <nav
                    class="flex w-full flex-col gap-3 group-data-[collapsible=icon]:items-center"
                    aria-label="Primary navigation"
                >
                    <Link
                        v-for="item in mainNavItems"
                        :key="item.title"
                        :href="item.href"
                        class="flex w-full items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                        :title="item.title"
                    >
                        <!-- Icon box — the ONLY element with bg + shadow -->
                        <span
                            :class="[
                                isCurrentUrl(item.href)
                                    ? iconBoxActive
                                    : iconBoxDefault,
                                'hover:bg-[#3a3a3a]',
                                isCurrentUrl(item.href)
                                    ? 'text-[#02cd86]'
                                    : 'text-white',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                class="size-[18px] shrink-0"
                            />
                        </span>

                        <!-- Text — plain, no background -->
                        <span
                            :class="[
                                'truncate text-sm font-medium group-data-[collapsible=icon]:sr-only',
                                isCurrentUrl(item.href)
                                    ? 'text-[#02cd86]'
                                    : 'text-white',
                            ]"
                        >
                            {{ item.title }}
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- ── Settings / account dropdown ────────────────────── -->
            <nav
                class="flex w-full flex-col gap-3 group-data-[collapsible=icon]:items-center"
                aria-label="Account navigation"
            >
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex w-full items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                            title="Settings"
                            type="button"
                            data-test="sidebar-menu-button"
                        >
                            <span
                                :class="[
                                    iconBoxDefault,
                                    'text-white hover:bg-[#3a3a3a]',
                                ]"
                            >
                                <Settings class="size-[18px] shrink-0" />
                            </span>
                            <span
                                class="truncate text-sm font-medium text-white group-data-[collapsible=icon]:sr-only"
                            >
                                Settings
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        class="w-64 rounded-lg"
                        :side="isMobile ? 'bottom' : 'right'"
                        align="end"
                        :side-offset="12"
                    >
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>
        </div>
    </Sidebar>
    <slot />
</template>
