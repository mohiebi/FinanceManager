<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartPie,
    LayoutGrid,
    PanelLeftClose,
    PanelLeftOpen,
    ReceiptText,
    Settings,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, report } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Report',
        href: report(),
        icon: ChartPie,
    },
    {
        title: 'Transactions',
        href: transactionsIndex(),
        icon: ReceiptText,
    },
];

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile, state, toggleSidebar } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

const sidebarItemClass =
    'flex h-9 w-full items-center gap-3 rounded-md px-3 transition-colors hover:bg-[#424242] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] group-data-[collapsible=icon]:w-9 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0';
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-0 p-0">
        <div
            class="flex h-full w-full flex-col justify-between bg-[#353535] px-6 pb-11 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-0"
        >
            <div
                class="flex w-full flex-col gap-14 pt-6 group-data-[collapsible=icon]:items-center"
            >
                <div
                    class="flex w-full justify-end group-data-[collapsible=icon]:justify-center"
                >
                    <button
                        :class="[sidebarItemClass, 'bg-[#2d2d2d] text-white']"
                        :title="
                            state === 'collapsed'
                                ? 'Expand sidebar'
                                : 'Collapse sidebar'
                        "
                        type="button"
                        data-sidebar="trigger"
                        data-slot="sidebar-trigger"
                        @click="toggleSidebar"
                    >
                        <PanelLeftOpen
                            v-if="isMobile || state === 'collapsed'"
                            class="size-5 shrink-0"
                        />
                        <PanelLeftClose v-else class="size-5 shrink-0" />
                        <span
                            class="truncate text-sm font-medium group-data-[collapsible=icon]:sr-only"
                        >
                            Menu toggle
                        </span>
                    </button>
                </div>

                <nav
                    class="flex w-full flex-col gap-4 group-data-[collapsible=icon]:items-center"
                    aria-label="Primary navigation"
                >
                    <Link
                        v-for="item in mainNavItems"
                        :key="item.title"
                        :href="item.href"
                        :class="[
                            sidebarItemClass,
                            isCurrentUrl(item.href)
                                ? 'bg-[#454545] text-[#02cd86]'
                                : 'bg-[#2d2d2d] text-white',
                        ]"
                        :title="item.title"
                    >
                        <component :is="item.icon" class="size-5 shrink-0" />
                        <span
                            class="truncate text-sm font-medium group-data-[collapsible=icon]:sr-only"
                        >
                            {{ item.title }}
                        </span>
                    </Link>
                </nav>
            </div>

            <nav
                class="flex w-full flex-col gap-4 group-data-[collapsible=icon]:items-center"
                aria-label="Account navigation"
            >
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            :class="[
                                sidebarItemClass,
                                'bg-[#2d2d2d] text-white',
                            ]"
                            title="Settings"
                            type="button"
                            data-test="sidebar-menu-button"
                        >
                            <Settings class="size-5 shrink-0" />
                            <span
                                class="truncate text-sm font-medium group-data-[collapsible=icon]:sr-only"
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
