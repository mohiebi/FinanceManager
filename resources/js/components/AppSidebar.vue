<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Crown,
    Lock,
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
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { dashboard as adminDashboard } from '@/routes/admin';

const { t } = useI18n();
const page = usePage();
const { navItems } = useModuleNav();
const { navigationName } = useNavigationNaming();
const { isCurrentUrl } = useCurrentUrl();

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
            data-sidebar-shell
            class="flex h-full min-h-0 w-full flex-col overflow-hidden bg-[#353535] px-5 pr-1 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-0"
        >
            <div
                class="flex min-h-0 w-full flex-1 flex-col gap-10 pt-6 group-data-[collapsible=icon]:items-center"
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
                    data-sidebar-scroll
                    class="sidebar-nav-scroll flex min-h-0 w-full flex-1 touch-pan-y flex-col gap-3 overflow-x-hidden overflow-y-auto overscroll-contain pe-1 pb-3 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:pe-0"
                    :aria-label="t('navigation.primary')"
                >
                    <Link
                        v-for="item in navItems"
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
                                item.state !== 'enabled' ? 'opacity-40' : '',
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
                            <!-- Pro, not bought yet — a lock rather than the
                                 free "+", so it doesn't promise a tap turns it on. -->
                            <Lock
                                v-else-if="item.state === 'locked'"
                                class="rtl:-right-auto absolute -top-1 -right-1 size-3 rounded-full bg-[#6C4EE9] p-px text-white rtl:-left-1"
                            />
                            <Crown
                                v-if="item.tier === 'pro'"
                                class="absolute -right-1 -bottom-1 hidden size-3 rounded-full bg-[#6C4EE9] p-px text-white group-data-[collapsible=icon]:block"
                                aria-hidden="true"
                            />
                        </span>

                        <!-- Text — one naming vocabulary at a time. -->
                        <span
                            class="min-w-0 truncate text-sm font-medium group-data-[collapsible=icon]:sr-only"
                            :class="[
                                isActive(item)
                                    ? 'text-[#02cd86]'
                                    : item.state !== 'enabled'
                                      ? 'text-white/40'
                                      : 'text-white',
                            ]"
                        >
                            {{ item.title }}
                        </span>

                        <!-- Product tier persists independently of whether the
                             module is enabled. Free modules only need a badge
                             while they are being promoted. -->
                        <span
                            v-if="
                                item.tier === 'pro' || item.state !== 'enabled'
                            "
                            :class="[
                                'ml-auto inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-medium group-data-[collapsible=icon]:hidden',
                                item.tier === 'pro'
                                    ? 'bg-[#6C4EE9]/15 text-[#a89bf3]'
                                    : 'bg-[#02CD86]/10 text-[#02CD86]',
                            ]"
                        >
                            <Crown
                                v-if="item.tier === 'pro'"
                                class="size-3"
                                aria-hidden="true"
                            />
                            {{ t(`modules.tiers.${item.tier}`) }}
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- ── Settings / account dropdown ────────────────────── -->
            <nav
                data-sidebar-account
                class="mt-4 flex w-full shrink-0 flex-col gap-3 group-data-[collapsible=icon]:items-center"
                :aria-label="t('navigation.account')"
            >
                <Link
                    v-if="page.props.auth.isAdmin"
                    data-sidebar-admin
                    :href="adminDashboard()"
                    :aria-current="
                        isCurrentUrl(adminDashboard()) ? 'page' : undefined
                    "
                    class="flex w-full items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                    :title="
                        navigationName(
                            'settings.navigation.admin',
                            'settings.navigation.admin_subtitle',
                        )
                    "
                >
                    <span
                        :class="[
                            isCurrentUrl(adminDashboard())
                                ? iconBoxActive
                                : iconBoxDefault,
                            'hover:bg-[#3a3a3a]',
                            isCurrentUrl(adminDashboard())
                                ? 'text-[#02cd86]'
                                : 'text-white',
                        ]"
                    >
                        <ShieldCheck class="size-[18px] shrink-0" />
                    </span>
                    <span
                        class="flex min-w-0 flex-col items-center group-data-[collapsible=icon]:sr-only"
                    >
                        <span
                            :class="[
                                'truncate text-sm font-medium',
                                isCurrentUrl(adminDashboard())
                                    ? 'text-[#02cd86]'
                                    : 'text-white',
                            ]"
                        >
                            {{
                                navigationName(
                                    'settings.navigation.admin',
                                    'settings.navigation.admin_subtitle',
                                )
                            }}
                        </span>
                    </span>
                </Link>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="flex w-full cursor-pointer items-center gap-3 rounded-md py-0.5 group-data-[collapsible=icon]:justify-center focus-visible:ring-2 focus-visible:ring-[#02cd86] focus-visible:ring-offset-2 focus-visible:ring-offset-[#353535] focus-visible:outline-none"
                            :title="
                                navigationName(
                                    'settings.title',
                                    'navigation.settings_subtitle',
                                )
                            "
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
                                class="flex min-w-0 flex-col items-center group-data-[collapsible=icon]:sr-only"
                            >
                                <span
                                    class="truncate text-sm font-medium text-white"
                                >
                                    {{
                                        navigationName(
                                            'settings.title',
                                            'navigation.settings_subtitle',
                                        )
                                    }}
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

<style scoped>
.sidebar-nav-scroll {
    scrollbar-color: rgb(255 255 255 / 22%) transparent;
    scrollbar-width: thin;
}

.sidebar-nav-scroll::-webkit-scrollbar {
    width: 5px;
}

.sidebar-nav-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav-scroll::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgb(255 255 255 / 22%);
}

.sidebar-nav-scroll::-webkit-scrollbar-thumb:hover {
    background: rgb(255 255 255 / 34%);
}
</style>
