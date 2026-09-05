<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Crown, Lock, Plus, Settings, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sidebar } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useModuleNav } from '@/composables/useModuleNav';
import type { ModuleNavItem } from '@/composables/useModuleNav';
import { useNavigationNaming } from '@/composables/useNavigationNaming';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as editProfile } from '@/routes/profile';
import { index as transactionsIndex } from '@/routes/transactions';

const { t } = useI18n();
const page = usePage();
const { navGroups } = useModuleNav();
const { navigationName } = useNavigationNaming();
const { isCurrentUrl } = useCurrentUrl();

// Promo/locked items are discovery prompts, not the current page — they never
// look "active" even if their href happens to match.
function isActive(item: ModuleNavItem): boolean {
    // A promo item's href is the modules page — standing on it must not light
    // up every promo row at once. A locked item points at its own route now, so
    // it can be the current page like any other.
    return item.state !== 'promo' && isCurrentUrl(item.href);
}

const navRowClass =
    'flex items-center gap-2.5 rounded-[9px] px-2.5 py-2.5 text-sm transition-colors';

/**
 * The one exception in the shared chrome.
 *
 * Advisor carries the gold accent from every other page — it is how the Pro
 * feature announces itself, and the only gold anywhere in the sidebar. The
 * treatment holds in all three module states, so a locked Advisor still reads
 * as the thing worth unlocking; the lock and plus markers below are untouched.
 */
function isAdvisor(item: ModuleNavItem): boolean {
    return item.key === 'advisor';
}

const user = computed(() => page.props.auth.user);
const isRtl = computed(() => page.props.dir === 'rtl');
const isPro = computed(() => page.props.subscription?.is_pro ?? false);
const initial = computed(
    () => user.value?.name?.trim()?.[0]?.toUpperCase() ?? '?',
);
</script>

<template>
    <Sidebar
        :side="isRtl ? 'right' : 'left'"
        collapsible="none"
        variant="sidebar"
        class="border-0 p-0"
        :style="{ '--sidebar-width': '15.25rem' }"
    >
        <div
            data-sidebar-shell
            class="flex h-full min-h-0 w-full flex-col overflow-hidden border-white/7 bg-[#0d0d0d] px-3 ltr:border-r rtl:border-l"
        >
            <div class="flex min-h-0 w-full flex-1 flex-col gap-5 pt-[18px]">
                <!-- ── Brand ────────────────────────────────────────── -->
                <Link
                    :href="dashboard()"
                    class="flex shrink-0 items-center gap-2.5 px-2"
                >
                    <img
                        src="/favicon.svg"
                        alt=""
                        class="h-8 w-[21px] shrink-0"
                    />
                    <span
                        class="truncate text-[16px] font-semibold tracking-[-0.01em] text-white"
                    >
                        CashPilot
                    </span>
                </Link>

                <!-- ── Primary action ───────────────────────────────── -->
                <Link
                    :href="transactionsIndex()"
                    class="flex shrink-0 items-center justify-center gap-1.5 rounded-[11px] bg-[#02cd86] px-4 py-2.5 text-sm font-medium text-[#101010] transition-colors hover:bg-[#14e096]"
                >
                    <Plus class="size-4" />
                    {{ t('navigation.add_transaction') }}
                </Link>

                <!-- ── Grouped navigation ───────────────────────────── -->
                <nav
                    data-sidebar-scroll
                    class="app-scroll-thin flex min-h-0 flex-1 touch-pan-y flex-col gap-5 overflow-x-hidden overflow-y-auto overscroll-contain pe-1 pb-3"
                    :aria-label="t('navigation.primary')"
                >
                    <div
                        v-for="group in navGroups"
                        :key="group.key"
                        class="flex flex-col gap-1"
                    >
                        <p
                            class="px-2.5 pb-1 text-[10px] font-medium tracking-[0.14em] text-[#5a5a5a] uppercase"
                        >
                            {{ group.label }}
                        </p>

                        <Link
                            v-for="item in group.items"
                            :key="item.key"
                            :href="item.href"
                            :class="[
                                navRowClass,
                                isAdvisor(item)
                                    ? 'border-s-2 border-s-[#d9c48f] bg-[#d9c48f]/10 font-medium text-white hover:bg-[#d9c48f]/15'
                                    : isActive(item)
                                      ? 'bg-[#252525] font-medium text-white'
                                      : item.state !== 'enabled'
                                        ? 'text-[#686868] hover:bg-white/5 hover:text-[#989898]'
                                        : 'font-normal text-[#989898] hover:bg-white/5 hover:text-white',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                class="size-[15px] shrink-0"
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                item.title
                            }}</span>

                            <span
                                v-if="
                                    item.tier === 'pro' ||
                                    item.state !== 'enabled'
                                "
                                :class="[
                                    'ml-auto inline-flex shrink-0 items-center gap-1',
                                    isAdvisor(item)
                                        ? 'advisor-mono rounded-[4px] border border-[#d9c48f]/35 px-[5px] py-[2px] text-[8.5px] tracking-[0.12em] text-[#d9c48f] uppercase'
                                        : 'rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                                    isAdvisor(item)
                                        ? ''
                                        : item.tier === 'pro'
                                          ? 'bg-[#6c4ee9]/15 text-[#a89bf3]'
                                          : 'bg-[#02cd86]/13 text-[#02cd86]',
                                ]"
                            >
                                <Crown
                                    v-if="
                                        item.tier === 'pro' && !isAdvisor(item)
                                    "
                                    class="size-2.5"
                                    aria-hidden="true"
                                />
                                <Lock
                                    v-else-if="
                                        item.state === 'locked' &&
                                        !isAdvisor(item)
                                    "
                                    class="size-2.5"
                                    aria-hidden="true"
                                />
                                <Plus
                                    v-else-if="
                                        item.state === 'promo' &&
                                        !isAdvisor(item)
                                    "
                                    class="size-2.5"
                                    aria-hidden="true"
                                />
                                {{ t(`modules.tiers.${item.tier}`) }}
                            </span>
                        </Link>
                    </div>
                </nav>
            </div>

            <!-- ── Settings / Admin — pinned, never scrolls away ────── -->
            <nav
                data-sidebar-account
                class="mt-2 flex w-full shrink-0 flex-col gap-1 border-t border-white/7 pt-2 pb-[14px]"
                :aria-label="t('navigation.account')"
            >
                <Link
                    :href="editProfile()"
                    :class="[
                        navRowClass,
                        isCurrentUrl(editProfile(), undefined, true)
                            ? 'bg-[#252525] font-medium text-white'
                            : 'font-normal text-[#989898] hover:bg-white/5 hover:text-white',
                    ]"
                >
                    <Settings class="size-[15px] shrink-0" />
                    <span class="min-w-0 flex-1 truncate">
                        {{
                            navigationName(
                                'settings.title',
                                'navigation.settings_subtitle',
                            )
                        }}
                    </span>
                </Link>

                <Link
                    v-if="page.props.auth.isAdmin"
                    data-sidebar-admin
                    :href="adminDashboard()"
                    :aria-current="
                        isCurrentUrl(adminDashboard()) ? 'page' : undefined
                    "
                    :class="[
                        navRowClass,
                        isCurrentUrl(adminDashboard())
                            ? 'bg-[#252525] font-medium text-white'
                            : 'font-normal text-[#989898] hover:bg-white/5 hover:text-white',
                    ]"
                >
                    <ShieldCheck class="size-[15px] shrink-0" />
                    <span class="min-w-0 flex-1 truncate">
                        {{
                            navigationName(
                                'settings.navigation.admin',
                                'settings.navigation.admin_subtitle',
                            )
                        }}
                    </span>
                </Link>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-center gap-2.5 rounded-[9px] px-2.5 py-2 text-start transition-colors hover:bg-white/5"
                            data-test="sidebar-menu-button"
                        >
                            <span
                                class="grid size-[30px] shrink-0 place-items-center rounded-full bg-[#252525] text-xs font-medium text-white"
                            >
                                {{ initial }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium text-white"
                                >
                                    {{ user?.name }}
                                </span>
                                <span
                                    class="block truncate text-xs text-[#686868]"
                                >
                                    {{
                                        isPro
                                            ? t('modules.tiers.pro')
                                            : t('modules.tiers.free')
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
