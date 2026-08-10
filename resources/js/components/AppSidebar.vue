<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    PanelLeftClose,
    PanelLeftOpen,
    Plus,
    Settings,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar, useSidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useModuleNav } from '@/composables/useModuleNav';
import type { ModuleNavItem } from '@/composables/useModuleNav';
import { dashboard as adminDashboard } from '@/routes/admin';

const { t } = useI18n();
const page = usePage();
const { navItems } = useModuleNav();
const { isCurrentUrl } = useCurrentUrl();

const mainNavItems = computed<ModuleNavItem[]>(() => {
    const items = [...navItems.value];

    if (page.props.auth.isAdmin) {
        items.push({
            key: 'admin',
            title: 'Admin',
            href: adminDashboard(),
            icon: ShieldCheck,
            state: 'enabled',
        });
    }

    return items;
});

// Promo items should not appear active while they are only discovery prompts.
function isActive(item: ModuleNavItem): boolean {
    return item.state === 'enabled' && isCurrentUrl(item.href);
}

const user = computed(() => page.props.auth.user);
const isRtl = computed(() => page.props.dir === 'rtl');
const { isMobile, state, toggleSidebar } = useSidebar();

const iconBoxBase =
    'relative flex h-9 w-9 shrink-0 items-center justify-center rounded-md shadow-[0_2px_6px_rgba(0,0,0,0.35)] transition-colors';
const iconBoxDefault = `${iconBoxBase} bg-[#2d2d2d]`;
const iconBoxActive = `${iconBoxBase} bg-[#454545]`;
</script>

<template>
    <Sidebar
        :side="isRtl ? 'right' : 'left'"
        collapsible="icon"
        variant="sidebar"
        class="border-0 p-0"
    >
        <div
            class="flex h-full w-full flex-col justify-between bg-[#353535] px-5 pb-11 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-0"
        >
            <div
                class="flex w-full flex-col gap-10 pt-6 group-data-[collapsible=icon]:items-center"
            >
                <!-- ── Menu toggle — full row is one button ───────── -->
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:outline-none"
                    data-sidebar="trigger"
                    :title="
                        state === 'collapsed'
                            ? t('navigation.expand_sidebar')
                            : t('navigation.collapse_sidebar')
                    "
                    @click="toggleSidebar"
                >
                    <!-- Icon box -->
                    <span
                        :class="[
                            iconBoxDefault,
                            'text-white hover:bg-[#3a3a3a]',
                        ]"
                    >
                        <PanelLeftOpen
                            v-if="isMobile || state === 'collapsed'"
                            class="size-[18px]"
                        />
                        <PanelLeftClose v-else class="size-[18px]" />
                    </span>
                    <!-- Text label — also triggers the toggle -->
                    <span
                        class="cursor-pointer truncate text-sm font-medium text-white/60 transition-colors group-data-[collapsible=icon]:sr-only hover:text-white"
                    >
                        {{ t('navigation.menu_toggle') }}
                    </span>
                </button>

                <!-- ── Primary navigation ──────────────────────────── -->
                <nav
                    class="flex w-full flex-col gap-3 group-data-[collapsible=icon]:items-center"
                    :aria-label="t('navigation.primary')"
                >
                    <Link
                        v-for="item in mainNavItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex w-full items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                        :title="item.title"
                    >
                        <!-- Icon box — the ONLY element with bg + shadow -->
                        <span
                            :class="[
                                isActive(item) ? iconBoxActive : iconBoxDefault,
                                'hover:bg-[#3a3a3a]',
                                isActive(item)
                                    ? 'text-[#02cd86]'
                                    : 'text-white',
                                item.state === 'promo' ? 'opacity-40' : '',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                class="size-[18px] shrink-0"
                            />
                            <Plus
                                v-if="item.state === 'promo'"
                                class="rtl:-right-auto absolute -top-1 -right-1 size-3 rounded-full bg-[#02cd86] text-[#353535] rtl:-left-1"
                            />
                        </span>

                        <!-- Text — plain, no background -->
                        <span
                            class="flex min-w-0 flex-col group-data-[collapsible=icon]:sr-only"
                        >
                            <span
                                :class="[
                                    'truncate text-sm font-medium',
                                    isActive(item)
                                        ? 'text-[#02cd86]'
                                        : item.state === 'promo'
                                          ? 'text-white/40'
                                          : 'text-white',
                                ]"
                            >
                                {{ item.title }}
                            </span>
                            <!-- The flight-themed aside — a wink, not a second
                                 label, so it stays quieter than the real one. -->
                            <span
                                v-if="item.subtitle"
                                class="truncate text-[11px] text-white/35"
                            >
                                ({{ item.subtitle }})
                            </span>
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- ── Settings / account dropdown ────────────────────── -->
            <nav
                class="flex w-full flex-col gap-3 group-data-[collapsible=icon]:items-center"
                :aria-label="t('navigation.account')"
            >
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex w-full cursor-pointer items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                            :title="t('settings.title')"
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
                                class="flex min-w-0 flex-col group-data-[collapsible=icon]:sr-only"
                            >
                                <span
                                    class="truncate text-sm font-medium text-white"
                                >
                                    {{ t('settings.title') }}
                                </span>
                                <span class="truncate text-[11px] text-white/35">
                                    ({{ t('navigation.settings_subtitle') }})
                                </span>
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        class="w-64"
                        side="top"
                        align="start"
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
