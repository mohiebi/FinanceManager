<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
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
            subtitle: 'Control tower',
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
                        </span>

                        <!-- Text — plain, no background. Not flex-1: sized to
                             its own content so `items-center` centers the
                             shorter line under the longer one, title or
                             subtitle, instead of centering in whatever room
                             is left in the row. -->
                        <span
                            class="flex min-w-0 flex-col items-center group-data-[collapsible=icon]:sr-only"
                        >
                            <span
                                :class="[
                                    'truncate text-sm font-medium',
                                    isActive(item)
                                        ? 'text-[#02cd86]'
                                        : item.state !== 'enabled'
                                          ? 'text-white/40'
                                          : 'text-white',
                                ]"
                            >
                                {{ item.subtitle ?? item.title }}
                            </span>
                            <!-- The plain functional name, kept as a quiet
                                 aside now that the flight name leads. -->
                            <span
                                v-if="item.subtitle"
                                class="truncate text-[11px] lowercase text-white/35"
                            >
                                {{ item.title }}
                            </span>
                        </span>

                        <!-- Off is never "you have to pay" — it just is not
                             switched on yet. Spelling that out here is the
                             only thing that stops the dimmed "+" items from
                             reading as a paywall. ml-auto rather than the text
                             column stretching, so the label stays put next to
                             the icon and only the badge floats to the edge. -->
                        <span
                            v-if="item.state !== 'enabled'"
                            :class="[
                                'ml-auto shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-medium group-data-[collapsible=icon]:hidden',
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
                                class="flex min-w-0 flex-col items-center group-data-[collapsible=icon]:sr-only"
                            >
                                <span
                                    class="truncate text-sm font-medium text-white"
                                >
                                    {{ t('navigation.settings_subtitle') }}
                                </span>
                                <span
                                    class="truncate text-[11px] lowercase text-white/35"
                                >
                                    {{ t('settings.title') }}
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
